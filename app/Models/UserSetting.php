<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'interface_locale',
        'ai_locale',
        'timezone',
        'country',
        'currency',
        'date_format',
        'theme',
        'notifications_enabled',
        'email_reminders',
        'quiet_hours_start',
        'quiet_hours_end',
        'external_ai_consent',
    ];

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'email_reminders' => 'boolean',
            'external_ai_consent' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
