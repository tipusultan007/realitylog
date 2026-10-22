<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'interface_locale' => $this->interface_locale,
            'ai_locale' => $this->ai_locale,
            'timezone' => $this->timezone,
            'country' => $this->country,
            'currency' => $this->currency,
            'date_format' => $this->date_format,
            'theme' => $this->theme,
            'notifications_enabled' => $this->notifications_enabled,
            'email_reminders' => $this->email_reminders,
            'quiet_hours_start' => $this->quiet_hours_start ? substr($this->quiet_hours_start, 0, 5) : null,
            'quiet_hours_end' => $this->quiet_hours_end ? substr($this->quiet_hours_end, 0, 5) : null,
            'external_ai_consent' => $this->external_ai_consent,
        ];
    }
}
