<?php

namespace App\Services\Search;

use App\Models\Memory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RecallService
{
    public function __construct(private readonly MemorySimilarityService $memorySimilarity) {}

    /**
     * Synonyms bridge Bangla and English, including common transliterations.
     *
     * @var array<string, array<int, string>>
     */
    private array $groups = [
        'phone' => ['phone', 'phones', 'mobile', 'smartphone', 'iphone', 'ফোন', 'ফোনের', 'মোবাইল', 'স্মার্টফোন'],
        'receipt' => ['receipt', 'receipts', 'invoice', 'bill', 'রিসিট', 'রিসিটের', 'রসিদ', 'বিল', 'চালান'],
        'purchase' => ['purchase', 'purchases', 'bought', 'buy', 'buying', 'order', 'ordered', 'payment', 'paid', 'pay', 'কেনা', 'কেনার', 'কিনেছি', 'কিনলাম', 'কিনেছিলাম', 'কিনেছিলে', 'ক্রয়', 'কেনাকাটা', 'কেনাকাটার', 'অর্ডার', 'পেমেন্ট', 'দাম'],
        'shop' => ['shop', 'store', 'merchant', 'seller', 'vendor', 'দোকান', 'স্টোর', 'বিক্রেতা', 'বিক্রি'],
        'money' => ['money', 'amount', 'price', 'cost', 'taka', 'tk', 'bdt', '৳', 'টাকা', 'দাম', 'মূল্য', 'পরিমাণ'],
        'laptop' => ['laptop', 'macbook', 'computer', 'notebook', 'ল্যাপটপ', 'কম্পিউটার'],
        'repair' => ['repair', 'repaired', 'fix', 'fixed', 'servicing', 'service', 'মেরামত', 'সারাই', 'ঠিক', 'সার্ভিসিং'],
        'warranty' => ['warranty', 'guarantee', 'ওয়ারেন্টি', 'গ্যারান্টি'],
        'meeting' => ['meeting', 'meetings', 'decision', 'decisions', 'minutes', 'মিটিং', 'সিদ্ধান্ত', 'বৈঠক'],
        'travel' => ['travel', 'trip', 'tour', 'journey', 'vacation', 'ভ্রমণ', 'ট্রিপ', 'ঘুরতে', 'ভ্রমণে'],
        'restaurant' => ['restaurant', 'restaurants', 'cafe', 'food', 'dinner', 'রেস্টুরেন্ট', 'ক্যাফে', 'খাবার', 'রাতের খাবার'],
        'rent' => ['rent', 'rental', 'lease', 'ভাড়া', 'বাড়িভাড়া', 'লিজ'],
        'home' => ['home', 'flat', 'apartment', 'house', 'বাড়ি', 'ফ্ল্যাট', 'বাসা', 'অ্যাপার্টমেন্ট'],
        'doctor' => ['doctor', 'hospital', 'clinic', 'appointment', 'ডাক্তার', 'হাসপাতাল', 'ক্লিনিক', 'অ্যাপয়েন্টমেন্ট'],
        'birthday' => ['birthday', 'anniversary', 'জন্মদিন', 'বার্ষিকী'],
        'fridge' => ['fridge', 'refrigerator', 'freezer', 'appliance', 'ফ্রিজ', 'ফ্রিজের', 'রেফ্রিজারেটর'],
        'appliance' => ['appliance', 'appliances', 'fridge', 'refrigerator', 'freezer', 'যন্ত্র', 'যন্ত্রটি', 'সরঞ্জাম'],
        'document' => ['document', 'agreement', 'contract', 'certificate', 'deed', 'নথি', 'চুক্তি', 'দলিল', 'সনদ'],
        'book' => ['book', 'books', 'reading', 'বই', 'বইয়ের', 'পড়া'],
        'bridge' => ['padma', 'bridge', 'পদ্মা', 'সেতু'],
        'car' => ['car', 'vehicle', 'bike', 'গাড়ি', 'বাইক', 'যানবাহন'],
        'subscription' => ['subscription', 'renewal', 'renew', 'সাবস্ক্রিপশন', 'নবায়ন'],
        'internet' => ['wifi', 'internet', 'router', 'broadband', 'ওয়াইফাই', 'ইন্টারনেট', 'রাউটার'],
        'insurance' => ['insurance', 'policy', 'বীমা', 'পলিসি'],
        'photo' => ['photo', 'photos', 'image', 'screenshot', 'ছবি', 'স্ক্রিনশট'],
        'event' => ['event', 'experience', 'ঘটনা', 'অভিজ্ঞতা'],
    ];

    private array $stopwords = [
        'where', 'did', 'do', 'does', 'i', 'my', 'me', 'the', 'a', 'an', 'is', 'was', 'were', 'of', 'to', 'for',
        'what', 'which', 'when', 'how', 'much', 'many', 'last', 'that', 'this', 'these', 'those', 'show', 'find',
        'everything', 'related', 'about', 'saved', 'save', 'saving', 'and', 'or', 'in', 'on', 'from', 'with', 'all',
        'please', 'can', 'you', 'tell', 'give', 'there', 'any', 'three', 'first',
        'কোথায়', 'কোথায়', 'আমি', 'আমার', 'কী', 'কি', 'কোন', 'কোনটি', 'কখন', 'কত', 'গত', 'মাসে', 'বছর', 'বছরে',
        'সেভ', 'করেছিলাম', 'করেছি', 'আছে', 'দেখাও', 'সবকিছু', 'এর', 'ও', 'এবং', 'একটি', 'একটা', 'আমাকে', 'বলো',
        'সম্পর্কে', 'সম্পর্কিত', 'কেন', 'কেনার', 'জন্য', 'করে', 'হয়েছিল', 'হয়েছে', 'ছিল', 'করে', 'টি', 'টা',
        'রেখেছিলাম', 'রেখেছি', 'রাখলাম', 'রাখা', 'থেকে', 'দিয়ে', 'সাথে', 'তার', 'তারপর', 'এখন', 'তখন', 'হলে',
        'পাওয়ার', 'হওয়ার', 'বলেছেন', 'গিয়েছিলাম', 'গিয়েছি', 'এসেছি', 'খেতে',
        'please', 'the', 'সব', 'দেখুন', 'খুঁজে', 'পাওয়া', 'কিভাবে', 'কীভাবে', 'যে', 'সেটা', 'কথা', 'বল', 'বলুন', 'মধ্যে', 'আগের',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function search(User $user, string $query, array $filters = []): array
    {
        $query = trim($query);
        $locale = $this->answerLocale($user);
        $dateRange = $this->parseDateRange($query);

        if ($query === '') {
            return $this->emptyResult($locale);
        }

        $tokens = $this->tokens($query);

        if (count($tokens) === 0) {
            return [
                ...$this->emptyResult($locale),
                'query' => $query,
                'too_short' => true,
            ];
        }

        $memories = $this->baseQuery($user, $filters, $dateRange)->get();
        $queryVector = $this->memorySimilarity->queryVector($query);
        $semanticEngine = $queryVector === null ? 'keywords' : 'local_embeddings';

        $scored = $memories
            ->map(fn (Memory $memory) => $this->scoreMemory($memory, $tokens, $query, $queryVector))
            ->filter(fn (array $item) => $item['score'] > 0)
            ->sortByDesc(fn (array $item) => [$item['score'], $item['memory']->event_date?->timestamp ?? $item['memory']->captured_at->timestamp])
            ->values();

        $total = $scored->count();
        $results = $scored->take(30)->values();

        $confidence = $this->confidence($scored->first()['score'] ?? 0);
        $intent = $this->intent($query);
        $purchaseList = $this->isPurchaseListQuestion($query);

        if ($purchaseList) {
            $purchases = $memories
                ->filter(fn (Memory $memory) => in_array($memory->memory_type, ['purchase', 'receipt'], true))
                ->sortByDesc(fn (Memory $memory) => $memory->event_date?->timestamp ?? $memory->captured_at->timestamp)
                ->take(3)
                ->values();

            if ($purchases->isNotEmpty()) {
                $sources = $purchases->map(fn (Memory $memory) => $this->sourceItem($memory, 'exact', $this->excerptFor($memory, $tokens), 20))->all();

                return [
                    'query' => $query,
                    'tokens' => $tokens,
                    'locale' => $locale,
                    'has_answer' => true,
                    'answer' => $this->purchaseListAnswer($purchases, $locale),
                    'answer_kind' => 'list',
                    'confidence' => 'high',
                    'confidence_label' => 'confidence_high',
                    'intent' => $intent,
                    'sources' => $sources,
                    'results' => $results->all(),
                    'total' => $total,
                    'date_range' => $dateRange,
                    'semantic_engine' => $semanticEngine,
                    'too_short' => false,
                ];
            }
        }

        if ($total === 0) {
            return [
                ...$this->emptyResult($locale, $semanticEngine),
                'query' => $query,
                'tokens' => $tokens,
                'date_range' => $dateRange,
            ];
        }

        $top = $scored->first();
        $hasAnswer = $top['score'] >= 6;

        return [
            'query' => $query,
            'tokens' => $tokens,
            'locale' => $locale,
            'has_answer' => $hasAnswer,
            'answer' => $hasAnswer ? $this->composeAnswer($top, $scored, $intent, $locale, $query) : null,
            'answer_kind' => $intent,
            'confidence' => $confidence,
            'confidence_label' => 'confidence_'.$confidence,
            'intent' => $intent,
            'sources' => $scored->take(4)->map(fn (array $item) => $this->sourceItem(
                $item['memory'],
                $item['match_type'],
                $this->excerptFor($item['memory'], $tokens),
                $item['score'],
                $item['similarity'],
                $item['vector_match']
            ))->all(),
            'results' => $results->all(),
            'total' => $total,
            'date_range' => $dateRange,
            'semantic_engine' => $semanticEngine,
            'too_short' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(User $user, array $filters, ?array $dateRange)
    {
        $query = $user->memories()
            ->with(['attachments', 'entities', 'tags', 'collections'])
            ->when(! ($filters['include_archived'] ?? false), fn ($builder) => $builder->where('is_archived', false))
            ->when(! empty($filters['type']) && $filters['type'] !== 'all', fn ($builder) => $builder->where('memory_type', $filters['type']))
            ->when(! empty($filters['language']) && $filters['language'] !== 'all', fn ($builder) => $builder->where('detected_language', $filters['language']))
            ->when($filters['favorite'] ?? false, fn ($builder) => $builder->where('is_favorite', true));

        if ($dateRange) {
            $query->where(function ($builder) use ($dateRange) {
                $builder->whereBetween('event_date', [$dateRange['start']->toDateString(), $dateRange['end']->toDateString()])
                    ->orWhere(function ($inner) use ($dateRange) {
                        $inner->whereNull('event_date')
                            ->whereBetween('captured_at', [$dateRange['start']->startOfDay(), $dateRange['end']->endOfDay()]);
                    });
            });
        } elseif (! empty($filters['date']) && $filters['date'] !== 'all') {
            [$start, $end] = match ($filters['date']) {
                'last_week' => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
                'last_month' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
                'last_year' => [now()->subYear()->startOfDay(), now()->endOfDay()],
                'this_year' => [now()->startOfYear(), now()->endOfDay()],
                default => [null, null],
            };

            if ($start) {
                $query->where(function ($builder) use ($start, $end) {
                    $builder->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
                        ->orWhere(function ($inner) use ($start, $end) {
                            $inner->whereNull('event_date')->whereBetween('captured_at', [$start, $end]);
                        });
                });
            }
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<int, float>|null  $queryVector
     * @return array{memory: Memory, score: float, match_type: string, exact_matches: int, semantic_matches: int, similarity: float, vector_match: bool}
     */
    private function scoreMemory(Memory $memory, array $tokens, string $query, ?array $queryVector = null): array
    {
        $vectorSimilarity = $this->memorySimilarity->similarity($memory->embedding, $queryVector);
        $vectorMatch = $vectorSimilarity >= (float) config('realitylog.ai.semantic_search_threshold', 0.45);
        $title = mb_strtolower($memory->title);
        $haystack = mb_strtolower(implode(' ', array_filter([
            $memory->title,
            $memory->summary,
            $memory->body,
            $memory->extracted_text,
            $memory->source_url,
            $memory->amount(),
            $memory->entities->pluck('name')->implode(' '),
            $memory->tags->pluck('name')->implode(' '),
            $memory->collections->pluck('name')->implode(' '),
            __('ui.types.'.$memory->memory_type, [], 'en').' '.__('ui.types.'.$memory->memory_type, [], 'bn'),
            __('ui.sources.'.$memory->source_type, [], 'en').' '.__('ui.sources.'.$memory->source_type, [], 'bn'),
        ])));

        $score = 0.0;
        $exact = 0;
        $semantic = 0;
        $lowerQuery = mb_strtolower(trim($query));

        foreach ($tokens as $token) {
            $direct = $this->contains($haystack, $token);

            if ($direct) {
                $exact++;
                $score += 7;
                if ($this->contains($title, $token)) {
                    $score += 4;
                }
                if ($this->contains(mb_strtolower($memory->tags->pluck('name')->implode(' ')), $token)) {
                    $score += 2;
                }

                continue;
            }

            $matchedSynonym = false;
            $titleSynonym = false;
            foreach ($this->variantsFor($token) as $variant) {
                if ($variant === $token || ! $this->contains($haystack, $variant)) {
                    continue;
                }

                $matchedSynonym = true;

                if ($this->contains($title, $variant)) {
                    $titleSynonym = true;
                }
            }

            if ($matchedSynonym) {
                $semantic++;
                $score += 4;
                if ($titleSynonym) {
                    $score += 2;
                }
            }
        }

        if ($exact === 0 && $semantic === 0 && ! $vectorMatch) {
            return [
                'memory' => $memory,
                'score' => 0.0,
                'match_type' => 'related',
                'exact_matches' => 0,
                'semantic_matches' => 0,
                'similarity' => round($vectorSimilarity, 4),
                'vector_match' => false,
            ];
        }

        $score += ($exact / max(count($tokens), 1)) * 8;
        $score += $semantic * 0.6;

        if ($vectorMatch) {
            $score += $vectorSimilarity * (float) config('realitylog.ai.semantic_score_weight', 10);
        }

        if (mb_strlen($lowerQuery) > 4 && $this->contains($haystack, $lowerQuery)) {
            $score += 10;
        }

        $recency = $memory->event_date ?? $memory->captured_at;
        if ($recency && $recency->gt(now()->subDays(45))) {
            $score += 1.5;
        }

        $matchType = match (true) {
            $exact >= max(1, ceil(count($tokens) / 2)) => 'exact',
            $semantic > 0, $vectorMatch => 'semantic',
            default => 'related',
        };

        return [
            'memory' => $memory,
            'score' => round($score, 2),
            'match_type' => $matchType,
            'exact_matches' => $exact,
            'semantic_matches' => $semantic,
            'similarity' => round($vectorSimilarity, 4),
            'vector_match' => $vectorMatch,
        ];
    }

    /**
     * @param  array{memory: Memory, score: float, match_type: string, exact_matches: int, semantic_matches: int}  $top
     * @param  Collection<int, array<string, mixed>>  $scored
     */
    private function composeAnswer(array $top, Collection $scored, string $intent, string $locale, string $query = ''): string
    {
        $memory = $top['memory'];
        $title = $memory->title;
        $date = ($memory->event_date ?? $memory->captured_at)->locale($locale)->translatedFormat('j F Y');
        $place = $memory->entities->firstWhere('type', 'place')?->name;
        $merchant = $memory->ai_metadata['merchant'] ?? $memory->entities->firstWhere('type', 'organization')?->name;
        $amount = $memory->amount();
        $asksMerchant = preg_match('/shop|store|merchant|vendor|seller|দোকান|বিক্রেতা/iu', $query) === 1;

        if ($intent === 'where') {
            if ($asksMerchant && $merchant) {
                return trans('ui.search.answer_merchant', ['title' => $title, 'merchant' => $merchant], $locale);
            }

            if ($place) {
                return trans('ui.search.answer_where', ['title' => $title, 'place' => $place], $locale);
            }

            if ($merchant) {
                return trans('ui.search.answer_merchant', ['title' => $title, 'merchant' => $merchant], $locale);
            }
        }

        if ($intent === 'when') {
            return trans('ui.search.answer_when', ['title' => $title, 'date' => $date], $locale);
        }

        if ($intent === 'amount' && $amount) {
            return trans('ui.search.answer_amount', ['title' => $title, 'amount' => $amount], $locale);
        }

        if ($scored->count() > 1 && $top['score'] < 14) {
            return trans('ui.search.answer_clarify', ['title' => $title], $locale);
        }

        $type = trans('ui.types.'.$memory->memory_type, [], $locale);

        return trans('ui.search.answer_general', [
            'count' => $scored->count(),
            'label' => Str::lower($type),
            'title' => $title,
            'date' => $date,
        ], $locale);
    }

    /**
     * @param  Collection<int, Memory>  $purchases
     */
    private function purchaseListAnswer(Collection $purchases, string $locale): string
    {
        $items = $purchases->map(function (Memory $memory) use ($locale) {
            $date = ($memory->event_date ?? $memory->captured_at)->locale($locale)->translatedFormat('j M Y');
            $amount = $memory->amount() ?: trans('ui.common.not_set', [], $locale);

            return '“'.$memory->title.'” — '.$amount.' ('.$date.')';
        })->implode('; ');

        return trans('ui.search.answer_list', ['items' => $items], $locale);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function excerptFor(Memory $memory, array $tokens, int $length = 190): string
    {
        $text = trim(implode(' ', array_filter([$memory->summary, $memory->body, $memory->extracted_text])));
        $text = preg_replace('/\s+/u', ' ', strip_tags($text)) ?: '';

        if ($text === '') {
            return $memory->typeLabel().' · '.($memory->source_url ?: $memory->sourceLabel());
        }

        $lower = mb_strtolower($text);
        $position = null;

        foreach ($tokens as $token) {
            $found = mb_strpos($lower, mb_strtolower($token));
            if ($found !== false && ($position === null || $found < $position)) {
                $position = $found;
            }
        }

        $start = max(0, ($position ?? 0) - 55);
        $excerpt = mb_substr($text, $start, $length);

        if ($start > 0) {
            $excerpt = '…'.$excerpt;
        }

        if (($start + $length) < mb_strlen($text)) {
            $excerpt .= '…';
        }

        return trim($excerpt);
    }

    private function intent(string $query): string
    {
        if (preg_match('/where|which shop|which store|কোথায়|কোথায়|কোন দোকান|কোন দোকানে/iu', $query)) {
            return 'where';
        }

        if (preg_match('/when|what date|which date|কখন|কোন তারিখে|কী তারিখে/iu', $query)) {
            return 'when';
        }

        if (preg_match('/how much|what did i pay|price|amount|cost|কত|দাম|খরচ/iu', $query)) {
            return 'amount';
        }

        return 'general';
    }

    private function isPurchaseListQuestion(string $query): bool
    {
        return preg_match('/last (three|3) purchases|শেষ তিন|শেষ ৩|শেষ তিনটি কেনাকাটা|last purchases|recent purchases/iu', $query) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $query): array
    {
        $tokens = preg_split('/[\s,.;:!?؟।“”"\'()\[\]\/]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($tokens)
            ->map(fn (string $token) => trim($token, '…-–—'))
            ->filter(fn (string $token) => mb_strlen($token) >= 2)
            ->reject(fn (string $token) => in_array($token, $this->stopwords, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function variantsFor(string $token): array
    {
        $variants = [$token];

        foreach ($this->groups as $group) {
            if (in_array($token, $group, true)) {
                $variants = array_merge($variants, $group);
            }
        }

        if (str_ends_with($token, 's') && mb_strlen($token) > 4) {
            $variants[] = mb_substr($token, 0, -1);
        }

        return array_values(array_unique($variants));
    }

    private function contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        if (preg_match('/^[a-z0-9\-\s]+$/i', $needle) === 1) {
            return preg_match('/\b'.preg_quote($needle, '/').'\w*/iu', $haystack) === 1;
        }

        return str_contains($haystack, $needle);
    }

    private function confidence(float $score): string
    {
        return match (true) {
            $score >= 18 => 'high',
            $score >= 9 => 'medium',
            default => 'low',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDateRange(string $query): ?array
    {
        $now = CarbonImmutable::now();

        if (preg_match('/last month|গত মাসে|গত মাস/iu', $query)) {
            return ['start' => $now->subMonthNoOverflow()->startOfMonth(), 'end' => $now->subMonthNoOverflow()->endOfMonth(), 'label' => 'last_month'];
        }

        if (preg_match('/this month|এই মাসে/iu', $query)) {
            return ['start' => $now->startOfMonth(), 'end' => $now->endOfMonth(), 'label' => 'this_month'];
        }

        if (preg_match('/last year|গত বছর|গত বছরে/iu', $query)) {
            return ['start' => $now->subYearNoOverflow()->startOfYear(), 'end' => $now->subYearNoOverflow()->endOfYear(), 'label' => 'last_year'];
        }

        if (preg_match('/this year|এই বছর|এ বছরে/iu', $query)) {
            return ['start' => $now->startOfYear(), 'end' => $now->endOfYear(), 'label' => 'this_year'];
        }

        if (preg_match('/last week|গত সপ্তাহে/iu', $query)) {
            return ['start' => $now->subDays(7)->startOfDay(), 'end' => $now->endOfDay(), 'label' => 'last_week'];
        }

        if (preg_match('/yesterday|গতকাল/iu', $query)) {
            return ['start' => $now->subDay()->startOfDay(), 'end' => $now->subDay()->endOfDay(), 'label' => 'yesterday'];
        }

        if (preg_match('/today|আজ/iu', $query)) {
            return ['start' => $now->startOfDay(), 'end' => $now->endOfDay(), 'label' => 'today'];
        }

        if (preg_match('/\b(20\d{2})\b/u', $query, $matches)) {
            $year = (int) $matches[1];

            return ['start' => $now->setYear($year)->startOfYear(), 'end' => $now->setYear($year)->endOfYear(), 'label' => (string) $year];
        }

        return null;
    }

    /**
     * @param  array{memory: Memory, score: float, match_type: string, exact_matches: int, semantic_matches: int, similarity: float, vector_match: bool}  $item
     * @return array<string, mixed>
     */
    private function sourceItem(Memory $memory, string $matchType, string $excerpt, float $score, float $similarity = 0.0, bool $vectorMatch = false): array
    {
        return [
            'memory' => $memory,
            'match_type' => $matchType,
            'excerpt' => $excerpt,
            'score' => $score,
            'similarity' => round($similarity, 4),
            'vector_match' => $vectorMatch,
            'date' => ($memory->event_date ?? $memory->captured_at)->translatedFormat('d M Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(string $locale, string $semanticEngine = 'keywords'): array
    {
        return [
            'query' => '',
            'tokens' => [],
            'locale' => $locale,
            'has_answer' => false,
            'answer' => null,
            'answer_kind' => null,
            'confidence' => 'low',
            'confidence_label' => 'confidence_low',
            'intent' => null,
            'sources' => [],
            'results' => [],
            'total' => 0,
            'date_range' => null,
            'semantic_engine' => $semanticEngine,
            'too_short' => false,
        ];
    }

    private function answerLocale(User $user): string
    {
        $locale = $user->settings?->ai_locale ?? app()->getLocale();

        return in_array($locale, ['en', 'bn'], true) ? $locale : 'en';
    }
}
