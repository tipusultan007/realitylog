<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemoryRequest;
use App\Http\Resources\MemoryResource;
use App\Services\Capture\CaptureService;
use Illuminate\Http\JsonResponse;

class CaptureController extends Controller
{
    public function store(StoreMemoryRequest $request, CaptureService $capture): JsonResponse
    {
        $memory = $capture->capture(
            $request->user(),
            $request->safe()->except('file'),
            $request->file('file')
        );

        $memory->load(['attachments', 'entities', 'tags', 'collections']);

        $message = $memory->processing_status === 'needs_review'
            ? __('ui.capture.captured_moment').' '.__('ui.memory.needs_review')
            : __('ui.capture.captured_moment');

        return response()->json([
            'message' => $message,
            'data' => MemoryResource::make($memory)->resolve(),
        ], 201);
    }
}
