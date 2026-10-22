<?php

namespace App\Services\Search;

use App\Models\Memory;
use App\Services\Ai\EmbeddingService;
use Throwable;

class MemorySimilarityService
{
    /**
     * @var array<string, array<int, float>|null>
     */
    private array $queryVectors = [];

    public function __construct(private readonly EmbeddingService $embeddings) {}

    /**
     * @return array<int, float>|null
     */
    public function queryVector(string $query): ?array
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        if (array_key_exists($query, $this->queryVectors)) {
            return $this->queryVectors[$query];
        }

        if (! config('realitylog.ai.enabled') || ! $this->embeddings->available()) {
            return $this->queryVectors[$query] = null;
        }

        try {
            $vectors = $this->embeddings->embed([$query]);
        } catch (Throwable $e) {
            report($e);

            return $this->queryVectors[$query] = null;
        }

        $vector = $vectors[0] ?? null;

        if (! is_array($vector) || $vector === []) {
            return $this->queryVectors[$query] = null;
        }

        $normalized = [];

        foreach ($vector as $value) {
            if (! is_numeric($value)) {
                return $this->queryVectors[$query] = null;
            }

            $normalized[] = (float) $value;
        }

        return $this->queryVectors[$query] = $normalized;
    }

    /**
     * @param  array<int, float|int>|null  $left
     * @param  array<int, float|int>|null  $right
     */
    public function similarity(?array $left, ?array $right): float
    {
        if (! is_array($left) || ! is_array($right)) {
            return 0.0;
        }

        $left = array_values($left);
        $right = array_values($right);

        if ($left === [] || count($left) !== count($right)) {
            return 0.0;
        }

        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;

        foreach ($left as $index => $value) {
            $other = $right[$index] ?? 0;

            if (! is_numeric($value) || ! is_numeric($other)) {
                return 0.0;
            }

            $value = (float) $value;
            $other = (float) $other;
            $dot += $value * $other;
            $leftNorm += $value * $value;
            $rightNorm += $other * $other;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dot / (sqrt($leftNorm) * sqrt($rightNorm))));
    }

    public function memorySimilarity(Memory $left, Memory $right): float
    {
        return $this->similarity($left->embedding, $right->embedding);
    }
}
