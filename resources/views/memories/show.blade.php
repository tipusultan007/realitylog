@extends('layouts.app')

@section('title', $memory->title)

@php
    $metadata = $memory->ai_metadata ?? [];
    $facts = $metadata['facts'] ?? [];
    $entityGroups = $memory->entities->groupBy('type');
    $entityIcons = [
        'person' => 'bi-person',
        'place' => 'bi-geo-alt',
        'item' => 'bi-box',
        'organization' => 'bi-building',
        'project' => 'bi-kanban',
    ];
    $statusClass = match ($memory->processing_status) {
        'ready' => 'green',
        'needs_review' => 'gold',
        'failed' => 'red',
        default => 'neutral',
    };
    $cover = $memory->coverAttachment();
    $relationshipLabels = [
        'related' => __('ui.memory.relationship_related'),
        'receipt' => __('ui.memory.relationship_receipt'),
        'repair' => __('ui.memory.relationship_repair'),
        'warranty' => __('ui.memory.relationship_warranty'),
        'follow_up' => __('ui.memory.relationship_follow_up'),
    ];
    $suggestionReasonLabels = [
        'similarity' => __('ui.memory.suggestion_reason_similarity'),
        'entity' => __('ui.memory.suggestion_reason_entity'),
        'tag' => __('ui.memory.suggestion_reason_tag'),
        'type' => __('ui.memory.suggestion_reason_type'),
    ];
@endphp

@section('content')
    <nav class="detail-breadcrumb" aria-label="{{ __('ui.nav.library') }}">
        <a href="{{ route('memories.index') }}">{{ __('ui.nav.library') }}</a>
        <i class="bi bi-chevron-right"></i>
        <span>{{ $memory->typeLabel() }}</span>
        <i class="bi bi-chevron-right"></i>
        <span class="text-truncate">{{ $memory->title }}</span>
    </nav>

    <header class="detail-header">
        <div>
            <span class="eyebrow">{{ $memory->typeLabel() }}</span>
            <h1>{{ $memory->title }}</h1>
            <div class="detail-meta">
                <span><i class="bi bi-calendar3"></i> {{ $memory->displayDateLong() }}</span>
                <span><i class="bi bi-chat-square-text"></i> {{ $memory->languageName() }}</span>
                <span><i class="bi bi-shield-lock"></i> {{ __('ui.common.private') }}</span>
                <span class="badge-soft {{ $statusClass }}">{{ $memory->statusLabel() }}</span>
                @if ($memory->is_favorite)
                    <span class="badge-soft orange"><i class="bi bi-heart-fill"></i> {{ __('ui.common.favorite') }}</span>
                @endif
            </div>
        </div>

        <div class="detail-actions">
            <form method="POST" action="{{ route('memories.favorite', $memory) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn-icon" title="{{ $memory->is_favorite ? __('ui.common.unfavorite') : __('ui.common.favorite') }}">
                    <i class="bi {{ $memory->is_favorite ? 'bi-heart-fill' : 'bi-heart' }}"></i>
                </button>
            </form>

            <a href="{{ route('memories.edit', $memory) }}" class="btn btn-soft btn-sm">
                <i class="bi bi-pencil"></i>
                {{ __('ui.common.edit') }}
            </a>

            @if ($memory->is_archived)
                <form method="POST" action="{{ route('memories.restore', $memory) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-soft btn-sm">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        {{ __('ui.common.restore') }}
                    </button>
                </form>
            @else
                <form
                    method="POST"
                    action="{{ route('memories.archive', $memory) }}"
                    data-confirm
                    data-confirm-title="{{ __('ui.memory.archive_title') }}"
                    data-confirm-body="{{ __('ui.memory.archive_body') }}"
                    data-confirm-label="{{ __('ui.common.archive') }}"
                >
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn-icon" title="{{ __('ui.common.archive') }}">
                        <i class="bi bi-archive"></i>
                    </button>
                </form>
            @endif

            <form
                method="POST"
                action="{{ route('memories.destroy', $memory) }}"
                data-confirm
                data-confirm-title="{{ __('ui.memory.delete_title') }}"
                data-confirm-body="{{ __('ui.memory.delete_body') }}"
                data-confirm-label="{{ __('ui.common.delete') }}"
                data-confirm-danger="true"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-icon" title="{{ __('ui.common.delete') }}" style="color: var(--danger);">
                    <i class="bi bi-trash3"></i>
                </button>
            </form>
        </div>
    </header>

    <div class="detail-layout">
        <div class="detail-main">
            @if ($memory->processing_status === 'needs_review')
                <div class="review-banner">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>
                        <strong>{{ __('ui.memory.needs_review') }}</strong><br>
                        {{ $memory->processing_error ?: __('ui.memory.review_help') }}
                        <div class="mt-2">
                            <a href="{{ route('memories.edit', $memory) }}" class="btn btn-soft btn-sm">{{ __('ui.memory.details_edit') }}</a>
                        </div>
                    </div>
                </div>
            @endif

            <div class="detail-cover" data-memory-type="{{ $memory->memory_type }}">
                @if ($cover && $cover->isImage())
                    <img src="{{ $cover->url() }}" alt="{{ $memory->title }}">
                @else
                    <span class="cover-abstract">
                        <span class="cover-glyph"><i class="bi {{ $memory->typeIcon() }}"></i></span>
                        <span class="cover-word">{{ $memory->typeLabel() }}</span>
                    </span>
                @endif
            </div>

            <article class="panel detail-content">
                @if ($memory->summary)
                    <section class="detail-section">
                        <h2>{{ __('ui.memory.summary') }}</h2>
                        <p class="detail-prose mb-0">{{ $memory->summary }}</p>
                    </section>
                @endif

                @if ($memory->body || $memory->source_url)
                    <section class="detail-section">
                        <h2>{{ __('ui.memory.original') }}</h2>
                        @if ($memory->body)
                            <div class="detail-prose">{!! nl2br(e($memory->body)) !!}</div>
                        @endif
                        @if ($memory->source_url)
                            <div class="mt-3">
                                <span class="small-caps text-muted-soft">{{ __('ui.memory.source_url') }}</span><br>
                                <a href="{{ $memory->source_url }}" target="_blank" rel="noopener noreferrer" class="detail-link">
                                    {{ $memory->source_url }}
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        @endif
                    </section>
                @endif

                @if ($memory->extracted_text)
                    <section class="detail-section">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <h2 class="mb-0">{{ __('ui.memory.extracted') }}</h2>
                            <span class="badge-soft neutral"><i class="bi bi-stars"></i> {{ __('ui.memory.provenance_ai') }}</span>
                        </div>
                        <div class="detail-prose">{!! nl2br(e($memory->extracted_text)) !!}</div>
                    </section>
                @endif

                @if (! empty($metadata) || $memory->event_date)
                    <section class="detail-section">
                        <h2>{{ __('ui.memory.details') }}</h2>
                        <div class="fact-grid">
                            @if ($memory->event_date)
                                <div class="fact">
                                    <span class="fact-label"><i class="bi bi-calendar-event"></i> {{ __('ui.common.event_date') }}</span>
                                    <span class="fact-value">{{ $memory->displayDateLong() }}</span>
                                </div>
                            @endif
                            @if (! empty($metadata['amount']))
                                <div class="fact">
                                    <span class="fact-label"><i class="bi bi-cash-stack"></i> {{ __('ui.types.purchase') }}</span>
                                    <span class="fact-value">{{ $metadata['amount'] }}</span>
                                    <span class="provenance">{{ __('ui.memory.provenance_ai') }}</span>
                                </div>
                            @endif
                            @if (! empty($metadata['merchant']))
                                <div class="fact">
                                    <span class="fact-label"><i class="bi bi-shop"></i> {{ __('ui.types.place') }}</span>
                                    <span class="fact-value">{{ $metadata['merchant'] }}</span>
                                    <span class="provenance">{{ __('ui.memory.provenance_ai') }}</span>
                                </div>
                            @endif
                            @if (! empty($metadata['warranty_until']))
                                <div class="fact">
                                    <span class="fact-label"><i class="bi bi-shield-check"></i> {{ __('ui.types.warranty') }}</span>
                                    <span class="fact-value">{{ $metadata['warranty_until'] }}</span>
                                    <span class="provenance">{{ __('ui.memory.provenance_ai') }}</span>
                                </div>
                            @endif
                            <div class="fact">
                                <span class="fact-label"><i class="bi bi-chat-square-text"></i> {{ __('ui.common.language') }}</span>
                                <span class="fact-value">{{ $memory->languageName() }}</span>
                            </div>
                            <div class="fact">
                                <span class="fact-label"><i class="bi bi-inbox"></i> {{ __('ui.common.source') }}</span>
                                <span class="fact-value">{{ $memory->sourceLabel() }}</span>
                            </div>
                        </div>
                    </section>
                @endif

                @if ($memory->entities->isNotEmpty())
                    <section class="detail-section">
                        <h2>{{ __('ui.memory.entities') }}</h2>
                        @foreach ($entityGroups as $entityType => $entities)
                            <div class="mb-3">
                                <span class="side-title">{{ __('ui.entity_types.'.$entityType) }}</span>
                                <div class="entity-group">
                                    @foreach ($entities as $entity)
                                        <span class="entity-pill">
                                            <i class="bi {{ $entityIcons[$entityType] ?? 'bi-tag' }}"></i>
                                            {{ $entity->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endif

                @if ($memory->tags->isNotEmpty() || $memory->collections->isNotEmpty())
                    <section class="detail-section">
                        <h2>{{ __('ui.memory.tags') }} &amp; {{ __('ui.memory.collections') }}</h2>
                        <div class="entity-group mb-2">
                            @foreach ($memory->tags as $tag)
                                <a href="{{ route('memories.index', ['q' => $tag->name]) }}" class="entity-pill">
                                    <i class="bi bi-tag"></i> {{ $tag->name }}
                                </a>
                            @endforeach
                        </div>
                        <div class="entity-group">
                            @foreach ($memory->collections as $collection)
                                <a href="{{ route('memories.index', ['collection' => $collection->id]) }}" class="entity-pill">
                                    <span class="collection-dot" style="background: {{ $collection->color }}"></span>
                                    {{ $collection->name }}
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($memory->attachments->isNotEmpty())
                    <section class="detail-section">
                        <h2>{{ __('ui.memory.attachment') }}</h2>
                        @foreach ($memory->attachments as $attachment)
                            <div class="attachment-file">
                                <span class="attachment-icon"><i class="bi {{ $attachment->isImage() ? 'bi-image' : 'bi-file-earmark-text' }}"></i></span>
                                <span class="min-w-0">
                                    <strong>{{ $attachment->original_name }}</strong>
                                    <span>
                                        {{ $attachment->kind }} · {{ $attachment->humanSize() }} ·
                                        <i class="bi bi-shield-lock"></i> {{ __('ui.memory.file_private') }}
                                    </span>
                                    @if ($attachment->duplicate_of_attachment_id)
                                        <span class="badge-soft neutral mt-1">
                                            <i class="bi bi-files"></i> {{ __('ui.attachments.duplicate') }}
                                        </span>
                                    @endif
                                </span>
                                <a href="{{ route('attachments.download', $attachment) }}" class="btn-icon" title="{{ __('ui.memory.download_original') }}" aria-label="{{ __('ui.memory.download_original') }}">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        @endforeach
                    </section>
                @endif

                <div class="notice-box mt-4">
                    <i class="bi bi-info-circle"></i>
                    <span>{{ __('ui.memory.ai_disclosure') }} {{ __('ui.memory.evidence_note') }}</span>
                </div>
            </article>
        </div>

        <aside class="detail-side">
            <section class="panel">
                <div class="panel-body">
                    <div class="side-title">
                        <span>{{ __('ui.memory.details') }}</span>
                        <a href="{{ route('memories.edit', $memory) }}"><i class="bi bi-pencil"></i></a>
                    </div>
                    <div class="side-list">
                        <div class="side-list-row">
                            <span>{{ __('ui.common.captured') }}</span>
                            <strong>{{ $memory->captured_at->translatedFormat('d M Y, H:i') }}</strong>
                        </div>
                        <div class="side-list-row">
                            <span>{{ __('ui.common.event_date') }}</span>
                            <strong>{{ $memory->event_date?->translatedFormat('d M Y') ?? __('ui.common.not_set') }}</strong>
                        </div>
                        <div class="side-list-row">
                            <span>{{ __('ui.memory.processing_status') }}</span>
                            <strong><span class="badge-soft {{ $statusClass }}">{{ $memory->statusLabel() }}</span></strong>
                        </div>
                        <div class="side-list-row">
                            <span>{{ __('ui.common.language') }}</span>
                            <strong>{{ $memory->languageName() }}</strong>
                        </div>
                        <div class="side-list-row">
                            <span>{{ __('ui.common.source') }}</span>
                            <strong>{{ $memory->sourceLabel() }}</strong>
                        </div>
                        <div class="side-list-row">
                            <span>{{ __('ui.common.privacy') }}</span>
                            <strong><i class="bi bi-lock"></i> {{ __('ui.common.private') }}</strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-body">
                    <div class="side-title">
                        <span>{{ __('ui.memory.timeline') }}</span>
                    </div>
                    <div class="detail-timeline">
                        <div class="detail-timeline-item">
                            <span class="detail-timeline-dot"></span>
                            <div class="detail-timeline-copy">
                                <strong>{{ __('ui.memory.timeline_captured') }}</strong>
                                <span>{{ $memory->captured_at->translatedFormat('d M Y, H:i') }}</span>
                            </div>
                        </div>
                        @if ($memory->event_date)
                            <div class="detail-timeline-item">
                                <span class="detail-timeline-dot"></span>
                                <div class="detail-timeline-copy">
                                    <strong>{{ __('ui.memory.timeline_event') }}</strong>
                                    <span>{{ $memory->displayDateLong() }}</span>
                                </div>
                            </div>
                        @endif
                        @if ($memory->processingJobs->isNotEmpty())
                            <div class="detail-timeline-item">
                                <span class="detail-timeline-dot"></span>
                                <div class="detail-timeline-copy">
                                    <strong>{{ __('ui.memory.timeline_processed') }}</strong>
                                    <span>{{ $memory->processingJobs->sortByDesc('created_at')->first()->created_at->translatedFormat('d M Y, H:i') }}</span>
                                </div>
                            </div>
                        @endif
                        <div class="detail-timeline-item">
                            <span class="detail-timeline-dot"></span>
                            <div class="detail-timeline-copy">
                                <strong>{{ __('ui.memory.timeline_edited') }}</strong>
                                <span>{{ $memory->updated_at->translatedFormat('d M Y, H:i') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-body">
                    <div class="side-title">
                        <span>{{ __('ui.memory.related') }}</span>
                        <span class="badge-soft neutral">{{ $relatedMemories->count() }}</span>
                    </div>

                    @if ($relatedMemories->isEmpty())
                        <p class="text-muted-soft small mb-0">{{ __('ui.memory.no_related') }}</p>
                    @else
                        <div class="related-list">
                            @foreach ($relatedMemories as $related)
                                <a href="{{ route('memories.show', $related) }}" class="related-mini" data-memory-type="{{ $related->memory_type }}">
                                    <span class="related-mini-icon"><i class="bi {{ $related->typeIcon() }}"></i></span>
                                    <span class="related-mini-copy">
                                        <strong>{{ $related->title }}</strong>
                                        <span>{{ $related->typeLabel() }} · {{ $related->displayDate() }}</span>
                                    </span>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($suggestedMemories->isNotEmpty())
                        <div class="suggested-block">
                            <span class="side-title">{{ __('ui.memory.suggested_related') }}</span>
                            <p>{{ __('ui.memory.suggested_related_body') }}</p>
                            @foreach ($suggestedMemories as $suggestion)
                                @php
                                    $suggested = $suggestion['memory'];
                                    $sharedDetails = implode(', ', array_merge($suggestion['shared_entities'], $suggestion['shared_tags']));
                                @endphp
                                <div class="suggested-item mb-2" @if ($sharedDetails !== '') title="{{ $sharedDetails }}" @endif>
                                    <a href="{{ route('memories.show', $suggested) }}" class="related-mini" data-memory-type="{{ $suggested->memory_type }}">
                                        <span class="related-mini-icon"><i class="bi {{ $suggested->typeIcon() }}"></i></span>
                                        <span class="related-mini-copy">
                                            <strong>{{ $suggested->title }}</strong>
                                            <span>{{ $suggested->displayDate() }}</span>
                                        </span>
                                        <i class="bi bi-arrow-up-right"></i>
                                    </a>
                                    <div class="d-flex align-items-center justify-content-between gap-2 mt-1">
                                        <span class="badge-soft neutral">{{ $suggestionReasonLabels[$suggestion['reason']] ?? __('ui.memory.suggested_related') }}</span>
                                        <form method="POST" action="{{ route('memories.link', $memory) }}" class="m-0">
                                            @csrf
                                            <input type="hidden" name="to_memory_id" value="{{ $suggested->id }}">
                                            <input type="hidden" name="relationship_type" value="related">
                                            <button type="submit" class="btn btn-soft btn-sm py-1">
                                                <i class="bi bi-link-45deg"></i>
                                                {{ __('ui.memory.confirm_link') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('memories.link', $memory) }}" class="link-form">
                        @csrf
                        <label class="form-label" for="to_memory_id">{{ __('ui.memory.link_memory') }}</label>
                        <select name="to_memory_id" id="to_memory_id" class="form-select" required>
                            <option value="">{{ __('ui.memory.choose_memory') }}</option>
                            @foreach ($linkableMemories as $linkable)
                                <option value="{{ $linkable->id }}">{{ $linkable->title }}</option>
                            @endforeach
                        </select>
                        <select name="relationship_type" class="form-select" aria-label="{{ __('ui.memory.relationship') }}">
                            @foreach ($relationshipLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-soft btn-sm">
                            <i class="bi bi-link-45deg"></i>
                            {{ __('ui.memory.confirm_link') }}
                        </button>
                    </form>
                </div>
            </section>

            <section class="panel">
                <div class="panel-body">
                    <div class="side-title">
                        <span>{{ __('ui.nav.reminders') }}</span>
                        <a href="{{ route('reminders.index', ['memory' => $memory->id]) }}"><i class="bi bi-plus-lg"></i></a>
                    </div>

                    @php($memoryReminders = $memory->reminders->where('status', 'upcoming'))
                    @if ($memoryReminders->isEmpty())
                        <p class="text-muted-soft small mb-2">{{ __('ui.reminders.empty_upcoming_body') }}</p>
                        <a href="{{ route('reminders.index', ['memory' => $memory->id]) }}" class="btn btn-soft btn-sm w-100">
                            <i class="bi bi-bell"></i> {{ __('ui.memory.add_reminder') }}
                        </a>
                    @else
                        <div class="reminder-list">
                            @foreach ($memoryReminders as $reminder)
                                @include('partials.reminder-row', ['reminder' => $reminder])
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </aside>
    </div>
@endsection
