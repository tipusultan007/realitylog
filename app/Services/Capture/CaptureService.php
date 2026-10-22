<?php

namespace App\Services\Capture;

use App\Jobs\ProcessMemory;
use App\Models\AuditLog;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CaptureService
{
    public function __construct(
        private readonly AttachmentService $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function capture(User $user, array $data, ?UploadedFile $file = null): Memory
    {
        $sourceType = $data['source_type'] ?? 'text';
        $body = trim((string) ($data['body'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            $title = $this->fallbackTitle($sourceType, $body, $file, $data['source_url'] ?? null);
        }

        $memory = Memory::create([
            'user_id' => $user->id,
            'title' => Str::limit($title, 220, ''),
            'memory_type' => $this->initialType($sourceType),
            'source_type' => $sourceType,
            'source_url' => $sourceType === 'link' ? ($data['source_url'] ?? null) : null,
            'body' => $body !== '' ? $body : null,
            'detected_language' => in_array($data['language'] ?? null, ['en', 'bn'], true)
                ? $data['language']
                : ($this->containsBengali($title.' '.$body) ? 'bn' : 'en'),
            'event_date' => $data['event_date'] ?? null,
            'captured_at' => now(),
            'processing_status' => 'queued',
        ]);

        if ($file) {
            $this->attachments->store($memory, $file);
        }

        $this->attachTags($memory, (string) ($data['tags'] ?? ''));

        if (! empty($data['collection_id'])) {
            $collection = $user->collections()->whereKey($data['collection_id'])->first();
            if ($collection) {
                $memory->collections()->attach($collection);
            }
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'memory.created',
            'subject_type' => Memory::class,
            'subject_id' => $memory->id,
            'meta' => ['source_type' => $sourceType],
            'ip_address' => request()->ip(),
        ]);

        ProcessMemory::dispatch($memory->id);

        return $memory->refresh();
    }

    private function initialType(string $sourceType): string
    {
        return match ($sourceType) {
            'image' => 'document',
            'document' => 'document',
            'voice' => 'note',
            'link' => 'link',
            default => 'note',
        };
    }

    private function fallbackTitle(string $sourceType, string $body, ?UploadedFile $file, ?string $url): string
    {
        if ($file) {
            return pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        }

        if ($sourceType === 'link' && $url) {
            return parse_url($url, PHP_URL_HOST) ?: __('ui.capture.tab_link');
        }

        if ($body !== '') {
            return Str::limit(Str::squish(strip_tags($body)), 70);
        }

        return __('ui.memory.untitled').' — '.now()->translatedFormat('d M Y');
    }

    private function attachTags(Memory $memory, string $raw): void
    {
        $names = collect(preg_split('/[,،]+/u', $raw) ?: [])
            ->map(fn ($name) => trim(mb_strtolower($name)))
            ->filter(fn ($name) => $name !== '' && mb_strlen($name) <= 40)
            ->unique()
            ->take(10);

        foreach ($names as $name) {
            $tag = Tag::firstOrCreate(
                ['user_id' => $memory->user_id, 'name' => $name],
                ['color' => $this->tagColor($name)]
            );

            $memory->tags()->syncWithoutDetaching([$tag->id]);
        }
    }

    private function tagColor(string $name): string
    {
        $colors = ['#D95F3B', '#517A64', '#C08A3E', '#526A8A', '#8B5D6F', '#68756C'];
        $index = abs(crc32($name)) % count($colors);

        return $colors[$index];
    }

    private function containsBengali(string $text): bool
    {
        return preg_match('/[\x{0980}-\x{09FF}]/u', $text) === 1;
    }
}
