<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MemoryAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'human_size' => $this->humanSize(),
            'kind' => $this->kind,
            'status' => $this->status,
            'is_image' => $this->isImage(),
            'is_duplicate' => $this->duplicate_of_attachment_id !== null,
            'duplicate_of_attachment_id' => $this->duplicate_of_attachment_id,
            'url' => $this->urlPath(),
            'download_url' => $this->disk === 'public'
                ? $this->urlPath()
                : route('api.v1.attachments.download', $this, false),
        ];
    }

    private function urlPath(): string
    {
        if ($this->disk === 'public') {
            $url = Storage::disk('public')->url($this->path);

            return parse_url($url, PHP_URL_PATH) ?: $url;
        }

        return route('api.v1.attachments.show', $this, false);
    }
}
