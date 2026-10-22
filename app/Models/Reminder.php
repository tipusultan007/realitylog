<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = [
        'user_id',
        'memory_id',
        'title',
        'notes',
        'type',
        'due_at',
        'status',
        'notification_channel',
        'completed_at',
        'email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    public function typeLabel(): string
    {
        return __('ui.reminder_types.'.$this->type);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'upcoming' && $this->due_at->isPast();
    }

    public function daysAway(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->due_at->copy()->startOfDay(), false);
    }
}
