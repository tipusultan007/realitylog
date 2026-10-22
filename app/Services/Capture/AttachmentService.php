<?php

namespace App\Services\Capture;

use App\Models\Memory;
use App\Models\MemoryAttachment;
use App\Services\Security\FileScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    public function __construct(private readonly FileScanner $scanner) {}

    public function store(Memory $memory, UploadedFile $file): MemoryAttachment
    {
        $this->scanner->scan($file);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $filename = Str::uuid().'.'.$extension;
        $path = $file->storeAs('memories/'.$memory->user_id, $filename, 'local');
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize() ?: 0;
        $checksum = hash_file('sha256', (string) $file->getRealPath());
        $duplicate = $checksum === false
            ? null
            : MemoryAttachment::query()
                ->where('checksum', $checksum)
                ->where('size', $size)
                ->whereHas('memory', fn ($query) => $query->where('user_id', $memory->user_id))
                ->oldest('id')
                ->first();

        return MemoryAttachment::create([
            'memory_id' => $memory->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => $size,
            'checksum' => $checksum ?: null,
            'duplicate_of_attachment_id' => $duplicate?->id,
            'kind' => $this->kindFor($mime, $extension),
            'status' => 'stored',
        ]);
    }

    public function delete(MemoryAttachment $attachment): void
    {
        if ($attachment->disk === 'local' && $attachment->path) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $attachment->delete();
    }

    public function kindFor(string $mime, string $extension): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        if ($mime === 'application/pdf' || in_array($extension, ['pdf', 'doc', 'docx', 'txt', 'md'], true)) {
            return 'document';
        }

        return 'file';
    }
}
