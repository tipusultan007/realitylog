<?php

namespace App\Services\Ai;

use App\Models\Memory;
use Illuminate\Support\Str;

class EmbeddingService
{
    public function __construct(private readonly AiRuntime $runtime) {}

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embed(array $texts): array
    {
        $texts = array_values(array_filter(array_map(
            fn ($text): string => trim((string) $text),
            $texts
        ), fn (string $text): bool => $text !== ''));

        if (! config('realitylog.ai.enabled') || $texts === []) {
            return [];
        }

        $payload = json_encode(['texts' => $texts], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $result = $this->runtime->runPython('embed.py', [
            '--model',
            (string) config('realitylog.ai.embedding_model'),
        ], $payload);

        $vectors = $result['vectors'] ?? [];

        return array_values(array_filter(
            is_array($vectors) ? $vectors : [],
            fn ($vector): bool => is_array($vector) && count($vector) > 0
        ));
    }

    public function textForMemory(Memory $memory): string
    {
        $text = trim(implode("\n\n", array_filter([
            $memory->title,
            $memory->summary,
            $memory->body,
            $memory->extracted_text,
        ])));

        return $text === '' ? '' : Str::limit($text, 8000, '');
    }

    /**
     * @return array<int, float>|null
     */
    public function embedMemory(Memory $memory): ?array
    {
        $text = $this->textForMemory($memory);

        if ($text === '') {
            return null;
        }

        $vectors = $this->embed([$text]);

        return $vectors[0] ?? null;
    }

    public function available(): bool
    {
        return (bool) config('realitylog.ai.enabled') && $this->runtime->python() !== null;
    }
}
