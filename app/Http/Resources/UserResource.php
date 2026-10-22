<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'initials' => $this->initials(),
            'created_at' => $this->created_at?->toIso8601String(),
            'settings' => UserSettingResource::make($this->whenLoaded('settings')),
        ];
    }
}
