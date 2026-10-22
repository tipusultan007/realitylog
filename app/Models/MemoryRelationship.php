<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryRelationship extends Model
{
    protected $fillable = [
        'user_id',
        'from_memory_id',
        'to_memory_id',
        'relationship_type',
        'provenance',
        'is_confirmed',
    ];

    protected function casts(): array
    {
        return ['is_confirmed' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromMemory(): BelongsTo
    {
        return $this->belongsTo(Memory::class, 'from_memory_id');
    }

    public function toMemory(): BelongsTo
    {
        return $this->belongsTo(Memory::class, 'to_memory_id');
    }
}
