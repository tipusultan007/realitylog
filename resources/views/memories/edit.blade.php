@extends('layouts.app')

@section('title', __('ui.memory.edit_title'))

@php
    $types = ['purchase', 'receipt', 'document', 'person', 'place', 'event', 'repair', 'item', 'task', 'idea', 'travel', 'note', 'meeting', 'warranty', 'medical', 'link'];
    $tagValue = old('tags', $memory->tags->pluck('name')->implode(', '));
    $statusClass = match ($memory->processing_status) {
        'ready' => 'green',
        'needs_review' => 'gold',
        'failed' => 'red',
        default => 'neutral',
    };
@endphp

@section('content')
    <nav class="detail-breadcrumb" aria-label="{{ __('ui.nav.library') }}">
        <a href="{{ route('memories.index') }}">{{ __('ui.nav.library') }}</a>
        <i class="bi bi-chevron-right"></i>
        <a href="{{ route('memories.show', $memory) }}" class="text-truncate">{{ $memory->title }}</a>
        <i class="bi bi-chevron-right"></i>
        <span>{{ __('ui.common.edit') }}</span>
    </nav>

    <div class="page-head">
        <div>
            <span class="eyebrow">{{ __('ui.memory.detail') }}</span>
            <h1>{{ __('ui.memory.edit_title') }}</h1>
            <p>{{ __('ui.memory.edit_subtitle') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('memories.show', $memory) }}" class="btn btn-soft">
                <i class="bi bi-arrow-left"></i>
                {{ __('ui.common.cancel') }}
            </a>
        </div>
    </div>

    <div class="detail-layout">
        <div class="detail-main">
            <form method="POST" action="{{ route('memories.update', $memory) }}" class="panel">
                @csrf
                @method('PATCH')

                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('ui.memory.details') }}</h2>
                        <p class="panel-subtitle">{{ __('ui.memory.ai_disclosure') }}</p>
                    </div>
                    <span class="badge-soft {{ $statusClass }}">{{ $memory->statusLabel() }}</span>
                </div>

                <div class="panel-body">
                    <div class="form-grid">
                        <div class="span-2">
                            <label class="form-label" for="title">{{ __('ui.capture.note_title') }}</label>
                            <input
                                id="title"
                                type="text"
                                name="title"
                                value="{{ old('title', $memory->title) }}"
                                class="form-control form-control-lg @error('title') is-invalid @enderror"
                                required
                                maxlength="220"
                            >
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label" for="memory_type">{{ __('ui.search.filter_type') }}</label>
                            <select id="memory_type" name="memory_type" class="form-select @error('memory_type') is-invalid @enderror" required>
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected(old('memory_type', $memory->memory_type) === $type)>
                                        {{ __('ui.types.'.$type) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('memory_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label" for="detected_language">{{ __('ui.common.language') }}</label>
                            <select id="detected_language" name="detected_language" class="form-select @error('detected_language') is-invalid @enderror" required>
                                <option value="en" @selected(old('detected_language', $memory->detected_language) === 'en')>{{ __('ui.languages.en') }}</option>
                                <option value="bn" @selected(old('detected_language', $memory->detected_language) === 'bn')>{{ __('ui.languages.bn') }}</option>
                            </select>
                            @error('detected_language')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label" for="event_date">{{ __('ui.common.event_date') }}</label>
                            <input
                                id="event_date"
                                type="date"
                                name="event_date"
                                value="{{ old('event_date', $memory->event_date?->format('Y-m-d')) }}"
                                class="form-control @error('event_date') is-invalid @enderror"
                            >
                            @error('event_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label" for="tags">{{ __('ui.memory.tags') }}</label>
                            <input
                                id="tags"
                                type="text"
                                name="tags"
                                value="{{ $tagValue }}"
                                class="form-control @error('tags') is-invalid @enderror"
                                placeholder="{{ __('ui.capture.tags_placeholder') }}"
                                maxlength="300"
                            >
                            <div class="form-hint">{{ __('ui.capture.tags_hint') }}</div>
                            @error('tags')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="span-2">
                            <label class="form-label" for="summary">{{ __('ui.memory.summary') }}</label>
                            <textarea
                                id="summary"
                                name="summary"
                                rows="3"
                                class="form-control @error('summary') is-invalid @enderror"
                                maxlength="1000"
                            >{{ old('summary', $memory->summary) }}</textarea>
                            @error('summary')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="span-2">
                            <label class="form-label" for="body">{{ __('ui.memory.original') }}</label>
                            <textarea
                                id="body"
                                name="body"
                                rows="7"
                                class="form-control @error('body') is-invalid @enderror"
                                placeholder="{{ __('ui.capture.note_placeholder') }}"
                            >{{ old('body', $memory->body) }}</textarea>
                            @error('body')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="span-2">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <label class="form-label mb-0" for="extracted_text">{{ __('ui.memory.extracted') }}</label>
                                <span class="badge-soft neutral"><i class="bi bi-stars"></i> {{ __('ui.memory.provenance_ai') }}</span>
                            </div>
                            <textarea
                                id="extracted_text"
                                name="extracted_text"
                                rows="7"
                                class="form-control @error('extracted_text') is-invalid @enderror"
                                placeholder="{{ __('ui.memory.review_help') }}"
                            >{{ old('extracted_text', $memory->extracted_text) }}</textarea>
                            <div class="form-hint">{{ __('ui.memory.evidence_note') }}</div>
                            @error('extracted_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="settings-submit">
                        <a href="{{ route('memories.show', $memory) }}" class="btn btn-soft">{{ __('ui.common.cancel') }}</a>
                        <button type="submit" class="btn btn-accent">
                            <i class="bi bi-check2"></i>
                            {{ __('ui.memory.save_changes') }}
                        </button>
                    </div>
                </div>
            </form>

            @if ($memory->attachments->isNotEmpty())
                <section class="panel">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title">{{ __('ui.memory.attachment') }}</h2>
                            <p class="panel-subtitle">{{ __('ui.memory.file_private') }}</p>
                        </div>
                    </div>
                    <div class="panel-body">
                        @foreach ($memory->attachments as $attachment)
                            <div class="attachment-file">
                                <span class="attachment-icon"><i class="bi {{ $attachment->isImage() ? 'bi-image' : 'bi-file-earmark-text' }}"></i></span>
                                <span class="min-w-0">
                                    <strong>{{ $attachment->original_name }}</strong>
                                    <span>{{ $attachment->kind }} · {{ $attachment->humanSize() }}</span>
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
                    </div>
                </section>
            @endif
        </div>

        <aside class="detail-side">
            <section class="panel">
                <div class="panel-body">
                    <div class="side-title">
                        <span>{{ __('ui.common.updated') }}</span>
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="side-list">
                        <div class="side-list-row">
                            <span>{{ __('ui.common.captured') }}</span>
                            <strong>{{ $memory->captured_at->translatedFormat('d M Y, H:i') }}</strong>
                        </div>
                        <div class="side-list-row">
                            <span>{{ __('ui.common.source') }}</span>
                            <strong>{{ $memory->sourceLabel() }}</strong>
                        </div>
                        <div class="side-list-row">
                            <span>{{ __('ui.memory.processing_status') }}</span>
                            <strong><span class="badge-soft {{ $statusClass }}">{{ $memory->statusLabel() }}</span></strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-body">
                    <div class="side-title">
                        <span>{{ __('ui.memory.provenance_user') }}</span>
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <p class="text-muted-soft small">{{ __('ui.memory.edit_subtitle') }}</p>
                    <div class="notice-box">
                        <i class="bi bi-info-circle"></i>
                        <span>{{ __('ui.memory.ai_disclosure') }}</span>
                    </div>
                </div>
            </section>
        </aside>
    </div>
@endsection
