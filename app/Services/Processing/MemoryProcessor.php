<?php

namespace App\Services\Processing;

use App\Models\Entity;
use App\Models\Memory;
use App\Models\ProcessingJob;
use App\Models\Tag;
use App\Services\Ai\EmbeddingService;
use Illuminate\Support\Str;
use Throwable;

class MemoryProcessor
{
    public function __construct(
        private readonly TextExtractor $textExtractor,
        private readonly EmbeddingService $embedding,
    ) {}

    public function process(Memory $memory): Memory
    {
        $memory->update([
            'processing_status' => 'processing',
            'processing_error' => null,
        ]);

        $this->job($memory, 'intake', 'completed', ['source' => $memory->source_type]);

        try {
            $memory->load('attachments', 'tags');
            $extracted = [];
            $needsReview = false;
            $reviewMessage = null;

            foreach ($memory->attachments as $attachment) {
                if ($attachment->status === 'stored') {
                    $result = $this->textExtractor->extract($attachment);
                    $attachment->update([
                        'extracted_text' => $result['text'] ?: null,
                        'status' => $result['status'],
                    ]);

                    $this->job($memory, 'extract_text', $result['status'], [
                        'attachment_id' => $attachment->id,
                        'characters' => mb_strlen($result['text']),
                    ], $result['message']);

                    if ($result['text'] !== '') {
                        $extracted[] = $result['text'];
                    }

                    if (in_array($result['status'], ['needs_review', 'failed'], true)) {
                        $needsReview = true;
                        $reviewMessage ??= $result['message'];
                    }
                } elseif ($attachment->extracted_text) {
                    $extracted[] = $attachment->extracted_text;
                }
            }

            $text = trim(implode("\n\n", array_filter([
                $memory->body,
                $memory->extracted_text,
                ...$extracted,
            ])));

            $language = $this->detectLanguage($memory->title.' '.$text);
            [$type, $confidence, $keywords] = $this->classify($memory->title.' '.$text, $memory->source_type);
            $facts = $this->extractFacts($text);
            $summary = $this->summarize($text ?: $memory->body ?: '');

            $metadata = array_filter([
                'extractor' => 'realitylog-local-v1',
                'classifier' => 'realitylog-rules-v1',
                'category_confidence' => $confidence,
                'keywords' => $keywords,
                'amount' => $facts['amount'] ?? null,
                'merchant' => $facts['merchant'] ?? null,
                'warranty_until' => $facts['warranty_until'] ?? null,
                'facts' => $facts['facts'],
                'language_confidence' => $language === 'mixed' ? 0.7 : 0.92,
            ], fn ($value) => $value !== null);

            $hasReadableText = mb_strlen($text) > 0;
            $status = ($needsReview && ! $hasReadableText) ? 'needs_review' : 'ready';

            $memory->update([
                'detected_language' => $language === 'mixed' ? 'en' : $language,
                'memory_type' => $type,
                'extracted_text' => $extracted ? implode("\n\n", $extracted) : $memory->extracted_text,
                'summary' => $summary,
                'ai_metadata' => $metadata,
                'processing_status' => $status,
                'processing_error' => $status === 'needs_review' ? $reviewMessage : null,
            ]);

            $this->syncEntities($memory, $memory->title.' '.$text);
            $this->syncHashtags($memory, $memory->title.' '.$text);
            $embedded = $this->refreshEmbedding($memory);

            $this->job($memory, 'classify', 'completed', [
                'memory_type' => $type,
                'confidence' => $confidence,
            ]);
            $this->job($memory, 'index', 'completed', [
                'strategy' => 'bilingual-keyword-index',
                'keywords' => $keywords,
                'semantic_index' => $embedded,
            ]);

            if ($status === 'needs_review') {
                $this->job($memory, 'review_required', 'needs_review', [], $reviewMessage);
            }

            return $memory->fresh(['attachments', 'entities', 'tags']);
        } catch (Throwable $e) {
            report($e);

            $memory->update([
                'processing_status' => 'failed',
                'processing_error' => 'Processing failed. Your original record is safe and can be retried.',
            ]);

            $this->job($memory, 'process_memory', 'failed', [], $e->getMessage());

            return $memory->fresh();
        }
    }

    private function refreshEmbedding(Memory $memory): bool
    {
        if (! config('realitylog.ai.enabled')) {
            return false;
        }

        try {
            $vector = $this->embedding->embedMemory($memory);

            if ($vector === null) {
                return false;
            }

            $model = (string) config('realitylog.ai.embedding_model');

            $memory->forceFill([
                'embedding' => $vector,
                'embedding_model' => $model,
                'embedding_dimensions' => count($vector),
                'embedded_at' => now(),
            ])->save();

            $this->job($memory, 'embed', 'completed', [
                'model' => $model,
                'dimensions' => count($vector),
            ]);

            return true;
        } catch (Throwable $e) {
            report($e);

            $this->job($memory, 'embed', 'failed', [], $e->getMessage());

            return false;
        }
    }

    private function job(Memory $memory, string $type, string $status, array $meta = [], ?string $error = null): void
    {
        ProcessingJob::create([
            'memory_id' => $memory->id,
            'type' => $type,
            'status' => $status,
            'attempts' => 1,
            'error_message' => $error,
            'meta' => $meta,
            'started_at' => now(),
            'finished_at' => in_array($status, ['completed', 'failed', 'needs_review'], true) ? now() : null,
        ]);
    }

    public function detectLanguage(string $text): string
    {
        $hasBengali = preg_match('/[\x{0980}-\x{09FF}]/u', $text) === 1;
        $hasLatin = preg_match('/[A-Za-z]/', $text) === 1;

        if ($hasBengali && $hasLatin) {
            return 'mixed';
        }

        if ($hasBengali) {
            return 'bn';
        }

        return 'en';
    }

    /**
     * @return array{0: string, 1: float, 2: array<int, string>}
     */
    private function classify(string $text, string $sourceType): array
    {
        $text = mb_strtolower($text);

        $categories = [
            'receipt' => ['receipt', 'invoice', 'bill', 'রসিদ', 'রিসিট', 'চালান'],
            'warranty' => ['warranty', 'guarantee', 'ওয়ারেন্টি', 'গ্যারান্টি'],
            'repair' => ['repair', 'repaired', 'servicing', 'fixed', 'মেরামত', 'সারাই', 'ঠিক কর'],
            'purchase' => ['purchase', 'bought', 'buy ', 'order', 'payment', 'paid', 'কেনাকাটা', 'কিনেছি', 'কিনলাম', 'ক্রয়', 'অর্ডার', 'পেমেন্ট'],
            'travel' => ['trip', 'travel', 'tour', 'booking', 'ticket', 'hotel', 'ভ্রমণ', 'ট্রিপ', 'বুকিং', 'টিকিট', 'হোটেল'],
            'meeting' => ['meeting', 'decision', 'minutes', 'মিটিং', 'সিদ্ধান্ত', 'বৈঠক'],
            'medical' => ['doctor', 'hospital', 'appointment', 'medicine', 'ডাক্তার', 'হাসপাতাল', 'অ্যাপয়েন্টমেন্ট', 'ঔষধ'],
            'document' => ['agreement', 'contract', 'document', 'certificate', 'deed', 'চুক্তি', 'দলিল', 'নথি', 'সনদ'],
            'place' => ['restaurant', 'cafe', 'recommendation', 'রেস্টুরেন্ট', 'ক্যাফে', 'সুপারিশ'],
            'idea' => ['idea', 'thought', 'learning', 'ভাবনা', 'আইডিয়া', 'শেখা'],
            'person' => ['birthday', 'met with', 'জন্মদিন', 'দেখা হয়েছে'],
        ];

        foreach ($categories as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $matched = array_values(array_filter($keywords, fn ($item) => str_contains($text, $item)));

                    return [$type, 0.86, array_slice($matched, 0, 5)];
                }
            }
        }

        if ($sourceType === 'link') {
            return ['link', 0.55, ['saved link']];
        }

        if ($sourceType === 'image') {
            return ['document', 0.5, ['image']];
        }

        return ['note', 0.6, ['personal note']];
    }

    /**
     * @return array{amount?: string, merchant?: string, warranty_until?: string, facts: array<int, array<string, mixed>>}
     */
    private function extractFacts(string $text): array
    {
        $facts = [];
        $normalized = $this->normalizeDigits($text);
        $amount = null;
        $merchant = null;
        $warrantyUntil = null;

        if (preg_match('/(?:BDT|৳|Tk\.?|USD|\$)\s*([\d,]+(?:\.\d{1,2})?)/iu', $normalized, $matches)) {
            $currency = preg_match('/USD|\$/iu', $matches[0]) ? 'USD' : 'BDT';
            $amount = $currency.' '.number_format((float) str_replace(',', '', $matches[1]), 2, '.', ',');
            $amount = rtrim(rtrim($amount, '0'), '.');
            $facts[] = ['key' => 'amount', 'value' => $amount, 'provenance' => 'extracted', 'confidence' => 0.9];
        } elseif (preg_match('/([\d,]+(?:\.\d{1,2})?)\s*(?:taka|টাকা)/iu', $normalized, $matches)) {
            $amount = 'BDT '.number_format((float) str_replace(',', '', $matches[1]), 2, '.', ',');
            $amount = rtrim(rtrim($amount, '0'), '.');
            $facts[] = ['key' => 'amount', 'value' => $amount, 'provenance' => 'extracted', 'confidence' => 0.82];
        }

        if (preg_match('/(?:from|at|purchased from|bought from|কিনেছি|থেকে)\s+([A-Z][A-Za-z0-9&\'\-]+(?:\s+[A-Z][A-Za-z0-9&\'\-]+){0,3})/u', $text, $matches)) {
            $merchant = trim($matches[1]);
            $facts[] = ['key' => 'merchant', 'value' => $merchant, 'provenance' => 'extracted', 'confidence' => 0.7];
        }

        if (preg_match('/(?:warranty|guarantee|ওয়ারেন্টি|গ্যারান্টি)[^\n]{0,80}?(?:until|till|expires?|পর্যন্ত)\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4}|[0-9]{4}-[0-9]{2}-[0-9]{2})/iu', $normalized, $matches)) {
            $warrantyUntil = $matches[1];
            $facts[] = ['key' => 'warranty_until', 'value' => $warrantyUntil, 'provenance' => 'extracted', 'confidence' => 0.76];
        }

        return array_filter([
            'amount' => $amount,
            'merchant' => $merchant,
            'warranty_until' => $warrantyUntil,
            'facts' => $facts,
        ], fn ($value) => $value !== null);
    }

    private function summarize(string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        if ($text === '') {
            return null;
        }

        $sentence = preg_split('/(?<=[.!?।])\s+/u', $text)[0] ?? $text;

        return Str::limit($sentence, 220);
    }

    private function syncEntities(Memory $memory, string $text): void
    {
        $known = [
            ['Star Tech', 'organization', 'Star Tech'],
            ['Dhanmondi', 'place', 'Dhanmondi'],
            ['Dhaka', 'place', 'Dhaka'],
            ['Gulshan', 'place', 'Gulshan'],
            ['Banani', 'place', 'Banani'],
            ['Chattogram', 'place', 'Chattogram'],
            ['Sylhet', 'place', 'Sylhet'],
            ['Sundarbans', 'place', 'Sundarbans'],
            ['Padma Bridge', 'place', 'Padma Bridge'],
            ['Walton', 'organization', 'Walton'],
            ['Aarong', 'organization', 'Aarong'],
            ['Bashundhara', 'place', 'Bashundhara'],
            ['iPhone', 'item', 'iPhone'],
            ['MacBook', 'item', 'MacBook'],
            ['laptop', 'item', 'Laptop'],
            ['ফ্রিজ', 'item', 'ফ্রিজ'],
            ['ল্যাপটপ', 'item', 'ল্যাপটপ'],
            ['ফোন', 'item', 'ফোন'],
            ['ঢাকা', 'place', 'ঢাকা'],
        ];

        foreach ($known as [$needle, $type, $name]) {
            if (! str_contains($text, $needle)) {
                continue;
            }

            $entity = Entity::firstOrCreate(
                ['user_id' => $memory->user_id, 'type' => $type, 'name' => $name],
                ['metadata' => ['discovered_from' => 'memory_processing']]
            );

            $memory->entities()->syncWithoutDetaching([
                $entity->id => ['provenance' => 'extracted', 'confidence' => 0.8],
            ]);
        }
    }

    private function syncHashtags(Memory $memory, string $text): void
    {
        preg_match_all('/#([\p{L}\p{N}_-]{2,30})/u', $text, $matches);

        foreach (array_unique($matches[1] ?? []) as $name) {
            $tag = Tag::firstOrCreate(
                ['user_id' => $memory->user_id, 'name' => mb_strtolower($name)],
                ['color' => '#68756C']
            );

            $memory->tags()->syncWithoutDetaching([$tag->id]);
        }
    }

    private function normalizeDigits(string $text): string
    {
        return str_replace(
            ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $text
        );
    }
}
