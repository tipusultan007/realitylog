@extends('layouts.app')

@section('title', __('ui.capture.title'))

@php
    $initialSource = old('source_type', $defaultType);
    $sources = [
        'text' => ['icon' => 'bi-pencil', 'label' => __('ui.capture.tab_note')],
        'image' => ['icon' => 'bi-camera', 'label' => __('ui.capture.tab_photo')],
        'document' => ['icon' => 'bi-file-earmark-text', 'label' => __('ui.capture.tab_document')],
        'voice' => ['icon' => 'bi-mic', 'label' => __('ui.capture.tab_voice')],
        'link' => ['icon' => 'bi-link-45deg', 'label' => __('ui.capture.tab_link')],
    ];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">{{ __('ui.nav.capture') }}</span>
            <h1>{{ __('ui.capture.title') }}</h1>
            <p>{{ __('ui.capture.subtitle') }}</p>
        </div>
        <div class="page-actions">
            <span class="privacy-note">
                <i class="bi bi-shield-lock"></i>
                {{ __('ui.capture.privacy_note') }}
            </span>
        </div>
    </div>

    <div class="capture-layout">
        <form method="POST" action="{{ route('capture.store') }}" enctype="multipart/form-data" class="panel capture-panel" data-capture-form>
            @csrf
            <input type="hidden" name="source_type" value="{{ $initialSource }}">

            <div class="capture-tabs" role="tablist" aria-label="{{ __('ui.nav.capture') }}">
                @foreach ($sources as $source => $meta)
                    <button
                        type="button"
                        class="capture-tab {{ $initialSource === $source ? 'is-active' : '' }}"
                        data-capture-tab="{{ $source }}"
                        role="tab"
                        aria-selected="{{ $initialSource === $source ? 'true' : 'false' }}"
                    >
                        <i class="bi {{ $meta['icon'] }}"></i>
                        {{ $meta['label'] }}
                    </button>
                @endforeach
            </div>

            <section class="capture-tab-panel" data-capture-panel="text" @if ($initialSource !== 'text') hidden @endif>
                <h2>{{ __('ui.capture.note_title') }}</h2>
                <p>{{ __('ui.capture.note_body') }}</p>

                <div class="mb-3">
                    <label class="form-label" for="text-title">{{ __('ui.capture.note_title') }}</label>
                    <input
                        id="text-title"
                        type="text"
                        name="title"
                        value="{{ old('title') }}"
                        class="form-control form-control-lg"
                        placeholder="{{ __('ui.capture.note_title_placeholder') }}"
                        @disabled($initialSource !== 'text')
                    >
                </div>

                <div>
                    <label class="form-label" for="text-body">{{ __('ui.capture.note_body') }}</label>
                    <textarea
                        id="text-body"
                        name="body"
                        rows="7"
                        class="form-control @error('body') is-invalid @enderror"
                        placeholder="{{ __('ui.capture.note_placeholder') }}"
                        @disabled($initialSource !== 'text')
                    >{{ old('body') }}</textarea>
                    @error('body')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </section>

            @foreach (['image', 'document', 'voice'] as $source)
                <section class="capture-tab-panel" data-capture-panel="{{ $source }}" @if ($initialSource !== $source) hidden @endif>
                    <h2>{{ __('ui.capture.tab_'.$source) }}</h2>
                    <p>
                        @if ($source === 'image')
                            {{ __('ui.capture.photo_hint') }}
                        @elseif ($source === 'document')
                            {{ __('ui.capture.document_hint') }}
                        @else
                            {{ __('ui.capture.voice_hint') }}
                        @endif
                    </p>

                    <label class="dropzone" data-file-drop>
                        <input
                            type="file"
                            name="file"
                            accept="{{ $source === 'image' ? 'image/jpeg,image/png,image/webp' : ($source === 'document' ? '.pdf,.txt,.doc,.docx,.md,.csv' : 'audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/ogg,audio/webm') }}"
                            @disabled($initialSource !== $source)
                        >
                        <span class="dropzone-icon"><i class="bi {{ $source === 'image' ? 'bi-image' : ($source === 'document' ? 'bi-file-earmark-arrow-up' : 'bi-mic') }}"></i></span>
                        <strong>
                            @if ($source === 'image')
                                {{ __('ui.capture.photo_hint') }}
                            @elseif ($source === 'document')
                                {{ __('ui.capture.document_hint') }}
                            @else
                                {{ __('ui.capture.voice_hint') }}
                            @endif
                        </strong>
                        <span>
                            @if ($source === 'image')
                                {{ __('ui.capture.photo_hint_sub') }}
                            @elseif ($source === 'document')
                                {{ __('ui.capture.document_hint_sub') }}
                            @else
                                {{ __('ui.capture.voice_hint_sub') }}
                            @endif
                        </span>
                        <span class="file-name" data-file-name hidden></span>
                    </label>

                    @if ($source === 'voice')
                        <div class="privacy-note mt-3">
                            <i class="bi bi-info-circle"></i>
                            {{ __('ui.capture.voice_ready') }}
                        </div>
                    @endif

                    <div class="mt-3">
                        <label class="form-label" for="{{ $source }}-title">{{ __('ui.capture.note_title') }} <span class="text-muted-soft fw-normal">({{ __('ui.common.optional') }})</span></label>
                        <input
                            id="{{ $source }}-title"
                            type="text"
                            name="title"
                            value="{{ old('title') }}"
                            class="form-control"
                            placeholder="{{ __('ui.capture.note_title_placeholder') }}"
                            @disabled($initialSource !== $source)
                        >
                    </div>

                    <div class="mt-3">
                        <label class="form-label" for="{{ $source }}-body">{{ __('ui.capture.details') }} <span class="text-muted-soft fw-normal">({{ __('ui.common.optional') }})</span></label>
                        <textarea
                            id="{{ $source }}-body"
                            name="body"
                            rows="3"
                            class="form-control"
                            placeholder="{{ __('ui.capture.note_placeholder') }}"
                            @disabled($initialSource !== $source)
                        >{{ old('body') }}</textarea>
                    </div>

                    @error('file')
                        <div class="alert alert-danger mt-3 mb-0">{{ $message }}</div>
                    @enderror
                </section>
            @endforeach

            <section class="capture-tab-panel" data-capture-panel="link" @if ($initialSource !== 'link') hidden @endif>
                <h2>{{ __('ui.capture.tab_link') }}</h2>
                <p>{{ __('ui.capture.link_note') }}</p>

                <div class="mb-3">
                    <label class="form-label" for="link-url">{{ __('ui.capture.link_url') }}</label>
                    <input
                        id="link-url"
                        type="url"
                        name="source_url"
                        value="{{ old('source_url') }}"
                        class="form-control form-control-lg @error('source_url') is-invalid @enderror"
                        placeholder="{{ __('ui.capture.link_placeholder') }}"
                        @disabled($initialSource !== 'link')
                    >
                    @error('source_url')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="link-title">{{ __('ui.capture.note_title') }} <span class="text-muted-soft fw-normal">({{ __('ui.common.optional') }})</span></label>
                    <input
                        id="link-title"
                        type="text"
                        name="title"
                        value="{{ old('title') }}"
                        class="form-control"
                        placeholder="{{ __('ui.capture.note_title_placeholder') }}"
                        @disabled($initialSource !== 'link')
                    >
                </div>

                <div>
                    <label class="form-label" for="link-body">{{ __('ui.capture.link_note') }}</label>
                    <textarea
                        id="link-body"
                        name="body"
                        rows="4"
                        class="form-control"
                        placeholder="{{ __('ui.capture.link_note_placeholder') }}"
                        @disabled($initialSource !== 'link')
                    >{{ old('body') }}</textarea>
                </div>
            </section>

            <div class="capture-section">
                <div class="capture-section-head">
                    <h3>{{ __('ui.capture.details') }}</h3>
                    <p>{{ __('ui.capture.details_sub') }}</p>
                </div>

                <div class="capture-details-grid">
                    <div>
                        <label class="form-label" for="event_date">{{ __('ui.capture.event_date') }}</label>
                        <input
                            id="event_date"
                            type="date"
                            name="event_date"
                            value="{{ old('event_date') }}"
                            class="form-control @error('event_date') is-invalid @enderror"
                        >
                        <div class="form-hint">{{ __('ui.capture.event_date_hint') }}</div>
                        @error('event_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="language">{{ __('ui.capture.language') }}</label>
                        <select id="language" name="language" class="form-select">
                            <option value="">{{ __('ui.capture.language_auto') }}</option>
                            <option value="en" @selected(old('language') === 'en')>{{ __('ui.languages.en') }}</option>
                            <option value="bn" @selected(old('language') === 'bn')>{{ __('ui.languages.bn') }}</option>
                        </select>
                    </div>

                    <div class="span-2">
                        <label class="form-label" for="tags">{{ __('ui.capture.tags') }}</label>
                        <input
                            id="tags"
                            type="text"
                            name="tags"
                            value="{{ old('tags') }}"
                            class="form-control"
                            placeholder="{{ __('ui.capture.tags_placeholder') }}"
                        >
                        <div class="form-hint">{{ __('ui.capture.tags_hint') }}</div>
                    </div>

                    <div class="span-2">
                        <label class="form-label" for="collection_id">{{ __('ui.capture.collection') }}</label>
                        <select id="collection_id" name="collection_id" class="form-select">
                            <option value="">{{ __('ui.capture.no_collection') }}</option>
                            @foreach ($collections as $collection)
                                <option value="{{ $collection->id }}" @selected((string) old('collection_id') === (string) $collection->id)>
                                    {{ $collection->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="capture-submit-bar">
                <p>{{ __('ui.capture.supported') }}<br>{{ __('ui.capture.processing_note') }}</p>
                <button type="submit" class="btn btn-accent btn-lg">
                    <i class="bi bi-check2"></i>
                    {{ __('ui.capture.submit') }}
                </button>
            </div>
        </form>

        <aside class="capture-side">
            <div class="panel panel-pad">
                <div class="privacy-note mb-3">
                    <i class="bi bi-lock"></i>
                    {{ __('ui.memory.file_private') }}
                </div>

                <h2 class="panel-title mb-3">{{ __('ui.capture.processing_title') }}</h2>
                <div class="process-steps">
                    <div class="process-step">
                        <span class="process-step-number">1</span>
                        <div>
                            <strong>{{ __('ui.capture.processing_step_1') }}</strong>
                            <p>{{ __('ui.memory.evidence_note') }}</p>
                        </div>
                    </div>
                    <div class="process-step">
                        <span class="process-step-number">2</span>
                        <div>
                            <strong>{{ __('ui.capture.processing_step_2') }}</strong>
                            <p>{{ __('ui.landing.step_2_body') }}</p>
                        </div>
                    </div>
                    <div class="process-step">
                        <span class="process-step-number">3</span>
                        <div>
                            <strong>{{ __('ui.capture.processing_step_3') }}</strong>
                            <p>{{ __('ui.capture.needs_review_hint') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-pad">
                <h2 class="panel-title mb-2">{{ __('ui.dashboard.suggested') }}</h2>
                <div class="d-grid gap-2">
                    <a href="{{ route('search.index', ['q' => __('ui.search.suggestion_1')]) }}" class="suggestion-pill">
                        <i class="bi bi-search"></i> {{ __('ui.search.suggestion_1') }}
                    </a>
                    <a href="{{ route('search.index', ['q' => __('ui.search.suggestion_3')]) }}" class="suggestion-pill">
                        <i class="bi bi-search"></i> {{ __('ui.search.suggestion_3') }}
                    </a>
                </div>
            </div>
        </aside>
    </div>
@endsection
