<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $collections = $request->user()->collections()
            ->withCount('memories')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => CollectionResource::collection($collections)->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $collection = $request->user()->collections()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#D95F3B',
        ]);

        $collection->loadCount('memories');

        return response()->json([
            'message' => __('ui.settings.saved'),
            'data' => CollectionResource::make($collection)->resolve(),
        ], 201);
    }

    public function update(Request $request, Collection $collection): JsonResponse
    {
        $this->ensureOwned($request, $collection);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $collection->update([
            'name' => $validated['name'] ?? $collection->name,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $collection->description,
            'color' => $validated['color'] ?? $collection->color,
        ]);

        $collection->loadCount('memories');

        return response()->json([
            'message' => __('ui.collections.updated'),
            'data' => CollectionResource::make($collection)->resolve(),
        ]);
    }

    public function destroy(Request $request, Collection $collection): JsonResponse
    {
        $this->ensureOwned($request, $collection);

        $collection->delete();

        return response()->json([
            'message' => __('ui.collections.deleted'),
        ]);
    }

    private function ensureOwned(Request $request, Collection $collection): void
    {
        abort_unless($collection->user_id === $request->user()->id, 404);
    }
}
