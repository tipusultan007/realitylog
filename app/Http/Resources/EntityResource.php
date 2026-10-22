<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'label' => __('ui.entity_types.'.$this->type),
            'metadata' => $this->metadata,
            'provenance' => $this->pivot?->provenance,
            'confidence' => $this->pivot?->confidence,
        ];
    }
}
