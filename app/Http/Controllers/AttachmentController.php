<?php

namespace App\Http\Controllers;

use App\Models\MemoryAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function show(MemoryAttachment $attachment): BinaryFileResponse
    {
        $this->authorizeAttachment($attachment);

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

    public function download(MemoryAttachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function authorizeAttachment(MemoryAttachment $attachment): void
    {
        abort_unless($attachment->memory?->user_id === auth()->id(), 403);
    }
}
