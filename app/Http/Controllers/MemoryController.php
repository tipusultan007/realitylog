<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMemoryRequest;
use App\Jobs\ProcessMemory;
use App\Models\AuditLog;
use App\Models\Memory;
use App\Models\MemoryRelationship;
use App\Models\Tag;
use App\Services\Search\RelationshipSuggestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemoryController extends Controller
{
    public function index(Request $request): View
    {
        return $this->libraryView($request, 'library');
    }

    public function favorites(Request $request): View
    {
        return $this->libraryView($request, 'favorites');
    }

    public function archived(Request $request): View
    {
        return $this->libraryView($request, 'archive');
    }

    public function show(Request $request, Memory $memory, RelationshipSuggestionService $suggestions): View
    {
        $this->authorize('view', $memory);

        $memory->load(['attachments', 'entities', 'tags', 'collections', 'reminders', 'processingJobs']);

        $relationshipRows = MemoryRelationship::query()
            ->where('user_id', $request->user()->id)
            ->where('is_confirmed', true)
            ->where(fn ($query) => $query->where('from_memory_id', $memory->id)->orWhere('to_memory_id', $memory->id))
            ->with(['fromMemory.attachments', 'toMemory.attachments'])
            ->get();

        $relatedMemories = $relationshipRows
            ->map(fn (MemoryRelationship $relationship) => $relationship->from_memory_id === $memory->id
                ? $relationship->toMemory
                : $relationship->fromMemory)
            ->filter(fn (?Memory $related) => $related !== null && $related->id !== $memory->id)
            ->unique('id')
            ->values();

        $suggestedMemories = $suggestions->forMemory(
            $memory,
            $relatedMemories->pluck('id')->all(),
            (int) config('realitylog.ai.relationship_suggestion_limit', 3)
        );

        $linkableMemories = $request->user()->memories()
            ->active()
            ->whereKeyNot($memory->id)
            ->orderByDesc('captured_at')
            ->limit(60)
            ->get(['id', 'title', 'memory_type']);

        $this->rememberView($request, $memory);

        return view('memories.show', [
            'memory' => $memory,
            'relatedMemories' => $relatedMemories,
            'suggestedMemories' => $suggestedMemories,
            'linkableMemories' => $linkableMemories,
        ]);
    }

    public function edit(Memory $memory): View
    {
        $this->authorize('update', $memory);

        $memory->load(['attachments', 'tags']);

        return view('memories.edit', ['memory' => $memory]);
    }

    public function update(UpdateMemoryRequest $request, Memory $memory): RedirectResponse
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

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'memory.updated',
            'subject_type' => Memory::class,
            'subject_id' => $memory->id,
            'ip_address' => $request->ip(),
        ]);

        return redirect()
            ->route('memories.show', $memory)
            ->with('status', __('ui.memory.edit_saved'));
    }

    public function destroy(Request $request, Memory $memory): RedirectResponse
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

        return redirect()
            ->route('memories.index')
            ->with('status', __('ui.memory.deleted'));
    }

    public function favorite(Request $request, Memory $memory): RedirectResponse
    {
        $this->authorize('update', $memory);

        $memory->update(['is_favorite' => ! $memory->is_favorite]);

        return back()->with('status', $memory->is_favorite ? __('ui.memory.favorited') : __('ui.memory.unfavorited'));
    }

    public function archive(Request $request, Memory $memory): RedirectResponse
    {
        $this->authorize('update', $memory);

        $memory->update(['is_archived' => true]);

        return redirect()
            ->route('memories.index')
            ->with('status', __('ui.memory.archived'));
    }

    public function restore(Request $request, Memory $memory): RedirectResponse
    {
        $this->authorize('update', $memory);

        $memory->update(['is_archived' => false]);

        return redirect()
            ->route('memories.show', $memory)
            ->with('status', __('ui.memory.restored'));
    }

    public function link(Request $request, Memory $memory): RedirectResponse
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

        MemoryRelationship::firstOrCreate([
            'user_id' => $request->user()->id,
            'from_memory_id' => $memory->id,
            'to_memory_id' => $validated['to_memory_id'],
            'relationship_type' => $validated['relationship_type'],
        ], ['provenance' => 'user', 'is_confirmed' => true]);

        return back()->with('status', __('ui.memory.confirm_link'));
    }

    public function reprocess(Request $request, Memory $memory): RedirectResponse
    {
        $this->authorize('update', $memory);

        $memory->forceFill(['processing_status' => 'queued', 'processing_error' => null])->save();
        ProcessMemory::dispatch($memory->id);

        return back()->with('status', __('ui.capture.captured_moment'));
    }

    private function libraryView(Request $request, string $mode): View
    {
        $user = $request->user();
        $query = $user->memories()->with(['attachments', 'entities', 'tags']);

        if ($mode === 'archive') {
            $query->archived();
        } else {
            $query->active();
        }

        if ($mode === 'favorites') {
            $query->favorites();
        }

        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('summary', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhereHas('tags', fn ($tagQuery) => $tagQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($request->filled('type') && $request->query('type') !== 'all') {
            $query->where('memory_type', $request->query('type'));
        }

        if ($request->filled('language') && $request->query('language') !== 'all') {
            $query->where('detected_language', $request->query('language'));
        }

        if ($request->filled('collection')) {
            $query->whereHas('collections', fn ($collectionQuery) => $collectionQuery->whereKey($request->integer('collection')));
        }

        $sort = (string) $request->query('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest('captured_at'),
            'event' => $query->orderByRaw('event_date IS NULL, event_date DESC')->orderByDesc('captured_at'),
            'az' => $query->orderBy('title'),
            default => $query->latest('captured_at'),
        };
        $query->orderByDesc('id');

        $memories = $query->paginate(12)->withQueryString();
        $typeCounts = $user->memories()
            ->when($mode === 'archive', fn ($builder) => $builder->where('is_archived', true), fn ($builder) => $builder->where('is_archived', false))
            ->selectRaw('memory_type, COUNT(*) as total')
            ->groupBy('memory_type')
            ->orderByDesc('total')
            ->get();

        return view('memories.index', [
            'memories' => $memories,
            'mode' => $mode,
            'search' => $search,
            'typeCounts' => $typeCounts,
            'collections' => $user->collections()->withCount(['memories' => fn ($collectionQuery) => $collectionQuery->where('is_archived', false)])->orderBy('name')->get(),
            'totalForMode' => match ($mode) {
                'archive' => $user->memories()->archived()->count(),
                'favorites' => $user->memories()->active()->favorites()->count(),
                default => $user->memories()->active()->count(),
            },
            'sort' => $sort,
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

    private function rememberView(Request $request, Memory $memory): void
    {
        $recent = collect($request->session()->get('recent_memories', []))
            ->prepend($memory->id)
            ->unique()
            ->take(8)
            ->values()
            ->all();

        $request->session()->put('recent_memories', $recent);
    }
}
