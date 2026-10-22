<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMemoryRequest;
use App\Http\Resources\MemoryResource;
use App\Jobs\ProcessMemory;
use App\Models\AuditLog;
use App\Models\Memory;
use App\Models\MemoryRelationship;
use App\Models\Tag;
use App\Services\Search\RelationshipSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemoryController extends Controller
{
    private const TYPES = [
        'purchase', 'receipt', 'document', 'person', 'place', 'event', 'repair',
        'item', 'task', 'idea', 'travel', 'note', 'meeting', 'warranty', 'medical', 'link',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $mode = (string) $request->query('mode', 'library');
        $mode = in_array($mode, ['library', 'favorites', 'archive'], true) ? $mode : 'library';
        $search = trim(mb_substr((string) $request->query('q'), 0, 200));
        $type = (string) $request->query('type', 'all');
        $language = (string) $request->query('language', 'all');
        $sort = (string) $request->query('sort', 'newest');

        $query = $user->memories()->with(['attachments', 'entities', 'tags', 'collections']);

        match ($mode) {
            'archive' => $query->archived(),
            'favorites' => $query->active()->favorites(),
            default => $query->active(),
        };

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('summary', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhereHas('tags', fn ($tagQuery) => $tagQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        if (in_array($type, self::TYPES, true)) {
            $query->where('memory_type', $type);
        }

        if (in_array($language, ['en', 'bn'], true)) {
            $query->where('detected_language', $language);
        }

        if ($request->filled('collection')) {
            $query->whereHas('collections', fn ($collectionQuery) => $collectionQuery->whereKey($request->integer('collection')));
        }

        match ($sort) {
            'oldest' => $query->oldest('captured_at'),
            'event' => $query->orderByRaw('event_date IS NULL, event_date DESC')->orderByDesc('captured_at'),
            'az' => $query->orderBy('title'),
            default => $query->latest('captured_at'),
        };
        $query->orderByDesc('id');

        $perPage = max(5, min(50, (int) $request->query('per_page', 12)));
        $memories = $query->paginate($perPage);

        $typeCounts = $user->memories()
            ->when($mode === 'archive', fn ($builder) => $builder->where('is_archived', true), fn ($builder) => $builder->where('is_archived', false))
            ->when($mode === 'favorites', fn ($builder) => $builder->where('is_favorite', true))
            ->selectRaw('memory_type, COUNT(*) as total')
            ->groupBy('memory_type')
            ->orderByDesc('total')
            ->get();

        $totalForMode = match ($mode) {
            'archive' => $user->memories()->archived()->count(),
            'favorites' => $user->memories()->active()->favorites()->count(),
            default => $user->memories()->active()->count(),
        };

        return response()->json([
            'data' => MemoryResource::collection($memories->getCollection())->resolve(),
            'meta' => [
                'current_page' => $memories->currentPage(),
                'last_page' => $memories->lastPage(),
                'per_page' => $memories->perPage(),
                'total' => $memories->total(),
                'from' => $memories->firstItem(),
                'to' => $memories->lastItem(),
            ],
            'filters' => [
                'mode' => $mode,
                'q' => $search,
                'type' => in_array($type, self::TYPES, true) ? $type : 'all',
                'language' => in_array($language, ['en', 'bn'], true) ? $language : 'all',
                'sort' => in_array($sort, ['newest', 'oldest', 'event', 'az'], true) ? $sort : 'newest',
                'collection' => $request->integer('collection') ?: null,
            ],
            'total_for_mode' => $totalForMode,
            'type_counts' => $typeCounts->map(fn ($row) => [
                'type' => $row->memory_type,
                'label' => __('ui.types.'.$row->memory_type),
                'total' => (int) $row->total,
            ])->all(),
            'collections' => $user->collections()
                ->withCount(['memories' => fn ($collectionQuery) => $collectionQuery->where('is_archived', false)])
                ->orderBy('name')
                ->get()
                ->map(fn ($collection) => [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'description' => $collection->description,
                    'color' => $collection->color,
                    'memories_count' => $collection->memories_count,
                ])->all(),
        ]);
    }

    public function show(Request $request, Memory $memory, RelationshipSuggestionService $suggestions): JsonResponse
    {
        $this->authorize('view', $memory);

        $memory->load(['attachments', 'entities', 'tags', 'collections', 'reminders', 'processingJobs']);

        $relationshipRows = MemoryRelationship::query()
            ->where('user_id', $request->user()->id)
            ->where('is_confirmed', true)
            ->where(fn ($query) => $query->where('from_memory_id', $memory->id)->orWhere('to_memory_id', $memory->id))
            ->with(['fromMemory.attachments', 'toMemory.attachments'])
            ->get();

        $related = $relationshipRows
            ->map(function (MemoryRelationship $relationship) use ($memory): ?array {
                $related = $relationship->from_memory_id === $memory->id
                    ? $relationship->toMemory
                    : $relationship->fromMemory;

                if ($related === null || $related->id === $memory->id) {
                    return null;
                }

                return [
                    ...MemoryResource::make($related)->resolve(),
                    'relationship_id' => $relationship->id,
                    'relationship_type' => $relationship->relationship_type,
                ];
            })
            ->filter()
            ->unique('id')
            ->values();

        $suggested = $suggestions->forMemory(
            $memory,
            $related->pluck('id')->all(),
            (int) config('realitylog.ai.relationship_suggestion_limit', 3)
        )->map(fn (array $item) => [
            'memory' => MemoryResource::make($item['memory'])->resolve(),
            'score' => $item['score'],
            'similarity' => $item['similarity'],
            'reason' => $item['reason'],
            'shared_entities' => $item['shared_entities'],
            'shared_tags' => $item['shared_tags'],
        ])->all();

        $linkable = $request->user()->memories()
            ->active()
            ->whereKeyNot($memory->id)
            ->orderByDesc('captured_at')
            ->limit(60)
            ->get(['id', 'title', 'memory_type'])
            ->map(fn (Memory $option) => [
                'id' => $option->id,
                'title' => $option->title,
                'memory_type' => $option->memory_type,
                'type_label' => __('ui.types.'.$option->memory_type),
            ])->all();

        return response()->json([
            'data' => MemoryResource::make($memory)->resolve(),
            'related' => $related,
            'suggested' => $suggested,
            'linkable' => $linkable,
        ]);
    }

    public function update(UpdateMemoryRequest $request, Memory $memory): JsonResponse
    {
        $this->authorize('update', $memory);

        $validated = $request->validated();

        $memory->update([
            'title' => $validated['title'],
            'memory_type' => $validated['memory_type'],
            'body' => $validated['body'] ?? null,
            'extracted_text' => $validated['extracted_text'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'detected_language' => $validated['detected_language'],
        ]);

        $this->syncTags($memory, (string) ($validated['tags'] ?? ''));

        if ($request->has('collection_ids')) {
            $memory->collections()->sync($validated['collection_ids'] ?? []);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'memory.updated',
            'subject_type' => Memory::class,
            'subject_id' => $memory->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => __('ui.memory.edit_saved'),
            'data' => MemoryResource::make($memory->fresh(['attachments', 'entities', 'tags', 'collections']))->resolve(),
        ]);
    }

    public function destroy(Request $request, Memory $memory): JsonResponse
    {
        $this->authorize('delete', $memory);

        foreach ($memory->attachments as $attachment) {
            if ($attachment->disk === 'local') {
                Storage::disk('local')->delete($attachment->path);
            }
        }

        $memory->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'memory.deleted',
            'subject_type' => Memory::class,
            'subject_id' => $memory->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => __('ui.memory.deleted'),
        ]);
    }

    public function favorite(Request $request, Memory $memory): JsonResponse
    {
        $this->authorize('update', $memory);

        $memory->update(['is_favorite' => ! $memory->is_favorite]);

        return response()->json([
            'message' => $memory->is_favorite ? __('ui.memory.favorited') : __('ui.memory.unfavorited'),
            'is_favorite' => $memory->is_favorite,
        ]);
    }

    public function archive(Request $request, Memory $memory): JsonResponse
    {
        $this->authorize('update', $memory);

        $memory->update(['is_archived' => true]);

        return response()->json([
            'message' => __('ui.memory.archived'),
            'is_archived' => true,
        ]);
    }

    public function restore(Request $request, Memory $memory): JsonResponse
    {
        $this->authorize('update', $memory);

        $memory->update(['is_archived' => false]);

        return response()->json([
            'message' => __('ui.memory.restored'),
            'is_archived' => false,
        ]);
    }

    public function link(Request $request, Memory $memory): JsonResponse
    {
        $this->authorize('update', $memory);

        $validated = $request->validate([
            'to_memory_id' => [
                'required',
                'integer',
                Rule::exists('memories', 'id')->where('user_id', $request->user()->id),
                Rule::notIn([$memory->id]),
            ],
            'relationship_type' => ['required', Rule::in(['related', 'receipt', 'repair', 'warranty', 'follow_up'])],
        ]);

        $relationship = MemoryRelationship::firstOrCreate([
            'user_id' => $request->user()->id,
            'from_memory_id' => $memory->id,
            'to_memory_id' => $validated['to_memory_id'],
            'relationship_type' => $validated['relationship_type'],
        ], ['provenance' => 'user', 'is_confirmed' => true]);

        return response()->json([
            'message' => __('ui.memory.confirm_link'),
            'relationship_id' => $relationship->id,
        ], $relationship->wasRecentlyCreated ? 201 : 200);
    }

    public function unlink(Request $request, Memory $memory, MemoryRelationship $relationship): JsonResponse
    {
        $this->authorize('update', $memory);

        abort_unless(
            $relationship->user_id === $request->user()->id
            && in_array($memory->id, [$relationship->from_memory_id, $relationship->to_memory_id], true),
            404
        );

        $relationship->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'memory.unlinked',
            'subject_type' => Memory::class,
            'subject_id' => $memory->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => __('ui.memory.link_removed'),
        ]);
    }

    public function reprocess(Request $request, Memory $memory): JsonResponse
    {
        $this->authorize('update', $memory);

        $memory->forceFill(['processing_status' => 'queued', 'processing_error' => null])->save();
        ProcessMemory::dispatch($memory->id);

        return response()->json([
            'message' => __('ui.memory.reprocessed'),
            'data' => MemoryResource::make($memory->fresh(['attachments', 'entities', 'tags', 'collections']))->resolve(),
        ]);
    }

    private function syncTags(Memory $memory, string $raw): void
    {
        $names = collect(preg_split('/[,،]+/u', $raw) ?: [])
            ->map(fn ($name) => trim(mb_strtolower($name)))
            ->filter(fn ($name) => $name !== '' && mb_strlen($name) <= 40)
            ->unique()
            ->take(10);

        $tagIds = $names->map(function (string $name) use ($memory) {
            return Tag::firstOrCreate(
                ['user_id' => $memory->user_id, 'name' => $name],
                ['color' => '#68756C']
            )->id;
        });

        $memory->tags()->sync($tagIds);
    }
}
