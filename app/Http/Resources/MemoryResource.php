<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detailed = $request->routeIs('api.v1.memories.show') || $request->boolean('detail');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'memory_type' => $this->memory_type,
            'type_label' => $this->typeLabel(),
            'source_type' => $this->source_type,
            'source_label' => $this->sourceLabel(),
            'source_url' => $this->source_url,
            'body' => $this->body,
            'summary' => $this->summary,
            'excerpt' => $this->excerpt(),
            'extracted_text' => $this->when($detailed, $this->extracted_text),
            'detected_language' => $this->detected_language,
            'event_date' => $this->event_date?->toDateString(),
            'captured_at' => $this->captured_at?->toIso8601String(),
            'display_date' => $this->displayDate(),
            'processing_status' => $this->processing_status,
            'status_label' => $this->statusLabel(),
            'processing_error' => $this->processing_error,
            'ai_metadata' => $this->ai_metadata,
            'amount' => $this->amount(),
            'is_favorite' => $this->is_favorite,
            'is_archived' => $this->is_archived,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'collections' => CollectionResource::collection($this->whenLoaded('collections')),
            'attachments' => MemoryAttachmentResource::collection($this->whenLoaded('attachments')),
            'entities' => EntityResource::collection($this->whenLoaded('entities')),
            'reminders' => ReminderResource::collection($this->whenLoaded('reminders')),
            'cover' => $this->whenLoaded('attachments', function () {
                $attachment = $this->coverAttachment();

                return $attachment ? MemoryAttachmentResource::make($attachment)->resolve() : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
