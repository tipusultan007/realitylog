<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Memory extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'memory_type',
        'source_type',
        'source_url',
        'body',
        'extracted_text',
        'summary',
        'detected_language',
        'event_date',
        'captured_at',
        'processing_status',
        'processing_error',
        'ai_metadata',
        'embedding',
        'embedding_model',
        'embedding_dimensions',
        'embedded_at',
        'is_favorite',
        'is_archived',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'captured_at' => 'datetime',
            'ai_metadata' => 'array',
            'embedding' => 'array',
            'embedding_dimensions' => 'integer',
            'embedded_at' => 'datetime',
            'is_favorite' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MemoryAttachment::class);
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'memory_entities')
            ->withPivot(['provenance', 'confidence'])
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'memory_tag');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_memories')->withTimestamps();
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(ProcessingJob::class);
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(MemoryRelationship::class, 'from_memory_id');
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(MemoryRelationship::class, 'to_memory_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    public function scopeFavorites(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }

    public function scopeSearchable(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function typeLabel(): string
    {
        return __('ui.types.'.$this->memory_type);
    }

    public function typeIcon(): string
    {
        return self::iconForType($this->memory_type);
    }

    public static function iconForType(string $memoryType): string
    {
        return match ($memoryType) {
            'receipt' => 'bi-receipt',
            'purchase' => 'bi-bag-check',
            'repair' => 'bi-tools',
            'travel' => 'bi-airplane',
            'document' => 'bi-file-earmark-text',
            'meeting' => 'bi-people',
            'place' => 'bi-geo-alt',
            'idea' => 'bi-lightbulb',
            'warranty' => 'bi-shield-check',
            'medical' => 'bi-heart-pulse',
            'person' => 'bi-person',
            'link' => 'bi-link-45deg',
            'event' => 'bi-calendar-heart',
            default => 'bi-journal-text',
        };
    }

    public function sourceLabel(): string
    {
        return __('ui.sources.'.$this->source_type);
    }

    public function languageName(): string
    {
        return $this->detected_language === 'bn' ? __('ui.languages.bn') : __('ui.languages.en');
    }

    public function amount(): ?string
    {
        return $this->ai_metadata['amount'] ?? null;
    }

    public function displayDate(): string
    {
        return ($this->event_date ?? $this->captured_at)->translatedFormat('d M Y');
    }

    public function displayDateLong(): string
    {
        return ($this->event_date ?? $this->captured_at)->translatedFormat('j F Y');
    }

    public function coverAttachment(): ?MemoryAttachment
    {
        return $this->attachments->firstWhere('kind', 'image') ?? $this->attachments->first();
    }

    public function coverUrl(): ?string
    {
        $attachment = $this->coverAttachment();

        if (! $attachment) {
            return null;
        }

        return $attachment->url();
    }

    public function excerpt(int $length = 150): string
    {
        $text = $this->summary ?: $this->extracted_text ?: $this->body ?: '';

        return str(strip_tags($text))->squish()->limit($length)->toString();
    }

    public function greetingDate(): string
    {
        return now()->translatedFormat('l, j F');
    }

    public function statusLabel(): string
    {
        return __('ui.statuses.'.$this->processing_status);
    }
}
