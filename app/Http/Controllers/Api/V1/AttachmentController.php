<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemoryResource;
use App\Jobs\ProcessMemory;
use App\Models\Memory;
use App\Models\MemoryAttachment;
use App\Services\Capture\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    private const MAX_ATTACHMENTS = 12;

    public function show(Request $request, MemoryAttachment $attachment): BinaryFileResponse
    {
        $this->authorizeAttachment($request, $attachment);

        if ($attachment->disk === 'public') {
            abort(404);
        }

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return response()->file(Storage::disk($attachment->disk)->path($attachment->path), [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function download(Request $request, MemoryAttachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($request, $attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function store(
        Request $request,
        Memory $memory,
        AttachmentService $attachments,
    ): JsonResponse {
        abort_unless($memory->user_id === $request->user()->id, 403);

        $request->validate([
            'files' => ['required_without:file', 'array', 'max:8'],
            'files.*' => [
                'required',
                'file',
                'max:25600',
                'mimes:jpg,jpeg,png,webp,gif,pdf,txt,doc,docx,md,csv,mp3,m4a,wav,ogg,webm',
            ],
            'file' => [
                'nullable',
                'file',
                'max:25600',
                'mimes:jpg,jpeg,png,webp,gif,pdf,txt,doc,docx,md,csv,mp3,m4a,wav,ogg,webm',
            ],
        ], [
            'files.*.uploaded' => __('ui.attachments.upload_failed'),
            'files.*.max' => __('ui.attachments.upload_failed'),
            'files.*.mimes' => __('ui.attachments.upload_failed'),
            'file.uploaded' => __('ui.attachments.upload_failed'),
            'file.max' => __('ui.attachments.upload_failed'),
            'file.mimes' => __('ui.attachments.upload_failed'),
        ]);

        $files = array_values(array_filter([
            ...($request->file('files') ?? []),
            $request->file('file'),
        ]));

        if ($files === []) {
            throw ValidationException::withMessages([
                'files' => __('ui.attachments.upload_failed'),
            ]);
        }

        $existing = $memory->attachments()->count();

        if ($existing + count($files) > self::MAX_ATTACHMENTS) {
            throw ValidationException::withMessages([
                'files' => __('ui.attachments.limit', ['max' => self::MAX_ATTACHMENTS]),
            ]);
        }

        foreach ($files as $file) {
            $attachments->store($memory, $file);
        }

        $memory->forceFill(['processing_status' => 'queued', 'processing_error' => null])->save();
        ProcessMemory::dispatch($memory->id);
        $memory = $memory->refresh()->load(['attachments', 'entities', 'tags', 'collections']);

        return response()->json([
            'message' => __('ui.attachments.added'),
            'data' => MemoryResource::make($memory)->resolve(),
        ], 201);
    }

    public function destroy(
        Request $request,
        Memory $memory,
        MemoryAttachment $attachment,
        AttachmentService $attachments,
    ): JsonResponse {
        abort_unless($memory->user_id === $request->user()->id, 403);
        abort_unless($attachment->memory_id === $memory->id, 404);

        $attachments->delete($attachment);

        // Rebuild the readable text so removed evidence does not linger.
        $memory->forceFill([
            'extracted_text' => null,
            'processing_status' => 'queued',
            'processing_error' => null,
        ])->save();
        ProcessMemory::dispatch($memory->id);
        $memory = $memory->refresh()->load(['attachments', 'entities', 'tags', 'collections']);

        return response()->json([
            'message' => __('ui.attachments.removed'),
            'data' => MemoryResource::make($memory)->resolve(),
        ]);
    }

    private function authorizeAttachment(Request $request, MemoryAttachment $attachment): void
    {
        abort_unless($attachment->memory?->user_id === $request->user()->id, 403);
    }
}
