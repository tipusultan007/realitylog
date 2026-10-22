<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MemoryAttachment extends Model
{
    protected $fillable = [
        'memory_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'duplicate_of_attachment_id',
        'kind',
        'extracted_text',
        'status',
    ];

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    public function url(): string
    {
        if ($this->disk === 'public') {
            return Storage::disk('public')->url($this->path);
        }

        return route('attachments.show', $this);
    }

    public function humanSize(): string
    {
        $bytes = $this->size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0).' KB';
        }

        return $bytes.' B';
    }

    public function isImage(): bool
    {
        return $this->kind === 'image' || str_starts_with((string) $this->mime_type, 'image/');
    }
}
