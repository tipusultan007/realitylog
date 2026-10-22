<?php

namespace App\Services\Search;

use App\Models\Memory;
use Illuminate\Support\Collection;

class RelationshipSuggestionService
{
    public function __construct(private readonly MemorySimilarityService $similarity) {}

    /**
     * @param  array<int, int>  $excludeMemoryIds
     * @return Collection<int, array{
     *     memory: Memory,
     *     score: float,
     *     similarity: float,
     *     reason: string,
     *     shared_entities: array<int, string>,
     *     shared_tags: array<int, string>
     * }>
     */
    public function forMemory(Memory $memory, array $excludeMemoryIds = [], int $limit = 3): Collection
    {
        $memory->loadMissing(['entities', 'tags']);

        $limit = max(1, min(6, $limit));
        $threshold = (float) config('realitylog.ai.relationship_similarity_threshold', 0.40);
        $memoryEntityIds = $memory->entities->pluck('id')->all();
        $memoryTagIds = $memory->tags->pluck('id')->all();

        return Memory::query()
            ->where('user_id', $memory->user_id)
            ->active()
            ->whereKeyNot($memory->id)
            ->when($excludeMemoryIds !== [], fn ($query) => $query->whereNotIn('id', array_values($excludeMemoryIds)))
            ->with(['attachments', 'entities', 'tags'])
            ->get()
            ->map(function (Memory $candidate) use ($memory, $memoryEntityIds, $memoryTagIds, $threshold): ?array {
                $sharedEntities = $candidate->entities
                    ->filter(fn ($entity): bool => in_array($entity->id, $memoryEntityIds, true))
                    ->pluck('name')
                    ->unique()
                    ->values();

                $sharedTags = $candidate->tags
                    ->filter(fn ($tag): bool => in_array($tag->id, $memoryTagIds, true))
                    ->pluck('name')
                    ->unique()
                    ->values();

                $similarity = $this->similarity->memorySimilarity($memory, $candidate);
                $sameType = $candidate->memory_type === $memory->memory_type;
                $hasEvidence = $similarity >= $threshold
                    || $sharedEntities->isNotEmpty()
                    || $sharedTags->isNotEmpty()
                    || $sameType;

                if (! $hasEvidence) {
                    return null;
                }

                $reason = match (true) {
                    $similarity >= $threshold => 'similarity',
                    $sharedEntities->isNotEmpty() => 'entity',
                    $sharedTags->isNotEmpty() => 'tag',
                    default => 'type',
                };

                return [
                    'memory' => $candidate,
                    'score' => round(
                        ($similarity * 70) + ($sharedEntities->count() * 12) + ($sharedTags->count() * 8) + ($sameType ? 4 : 0),
                        2
                    ),
                    'similarity' => round($similarity, 4),
                    'reason' => $reason,
                    'shared_entities' => $sharedEntities->all(),
                    'shared_tags' => $sharedTags->all(),
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $item): array => [
                $item['score'],
                $item['memory']->event_date?->timestamp ?? $item['memory']->captured_at?->timestamp ?? 0,
            ])
            ->take($limit)
            ->values();
    }
}
