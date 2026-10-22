<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'notes' => $this->notes,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'due_at' => $this->due_at?->toIso8601String(),
            'status' => $this->status,
            'notification_channel' => $this->notification_channel,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'days_away' => $this->daysAway(),
            'memory' => MemoryResource::make($this->whenLoaded('memory')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
