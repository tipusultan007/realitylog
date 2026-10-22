@extends('layouts.app')

@section('title', __('ui.timeline.title'))

@php
    $types = ['purchase', 'receipt', 'document', 'person', 'place', 'event', 'repair', 'item', 'task', 'idea', 'travel', 'note', 'meeting', 'warranty', 'medical', 'link'];
@endphp

@section('content')
    <header class="timeline-header">
        <div>
            <span class="eyebrow">{{ __('ui.nav.timeline') }}</span>
            <h1>{{ __('ui.timeline.title') }}</h1>
            <p>{{ __('ui.timeline.subtitle') }}</p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <form method="GET" action="{{ route('timeline.index') }}">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <select name="type" class="form-select" data-auto-submit aria-label="{{ __('ui.timeline.filter_category') }}">
                    <option value="all">{{ __('ui.timeline.filter_category') }}: {{ __('ui.common.all') }}</option>
                    @foreach ($types as $timelineType)
                        <option value="{{ $timelineType }}" @selected($type === $timelineType)>{{ __('ui.types.'.$timelineType) }}</option>
                    @endforeach
                </select>
            </form>

            <div class="month-switcher">
                <a href="{{ route('timeline.index', ['month' => $previousMonth->format('Y-m'), 'type' => $type]) }}" aria-label="{{ __('ui.common.back') }}">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <span>{{ $month->translatedFormat('F Y') }}</span>
                <a href="{{ route('timeline.index', ['month' => $nextMonth->format('Y-m'), 'type' => $type]) }}" aria-label="{{ __('ui.common.more') }}">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </header>

    <div class="timeline-shell">
        <div class="timeline-month-label">
            <span>{{ $month->translatedFormat('F Y') }}</span>
            <span class="badge-soft neutral">{{ $monthTotal }} {{ __('ui.common.results') }}</span>
        </div>

        @if ($entries->isEmpty())
            <x-empty-state
                icon="bi-calendar3"
                :title="__('ui.timeline.no_events')"
                :body="__('ui.timeline.no_events_body')"
                :action-route="route('capture.create')"
                :action-label="__('ui.dashboard.add_first')"
            />
        @else
            @foreach ($entries as $date => $memories)
                @php($day = \Illuminate\Support\Carbon::parse($date))
                <section class="timeline-group">
                    <div class="timeline-date">
                        <div class="day">{{ $day->translatedFormat('d') }}</div>
                        <div class="weekday">{{ $day->translatedFormat('D') }}</div>
                        <div class="dot"></div>
                    </div>

                    <div class="timeline-cards">
                        @foreach ($memories as $memory)
                            <a href="{{ route('memories.show', $memory) }}" class="timeline-memory" data-memory-type="{{ $memory->memory_type }}">
                                <span class="timeline-thumb">
                                    <i class="bi {{ $memory->typeIcon() }}"></i>
                                </span>
                                <span class="timeline-copy">
                                    <h3>{{ $memory->title }}</h3>
                                    <p>
                                        {{ $memory->typeLabel() }}
                                        · {{ $memory->languageName() }}
                                        @if ($memory->event_date)
                                            · {{ __('ui.timeline.event_on', ['date' => $memory->displayDate()]) }}
                                        @else
                                            · {{ __('ui.timeline.captured_on', ['date' => $memory->displayDate()]) }}
                                        @endif
                                    </p>
                                </span>
                                <span class="badge-soft {{ $memory->is_favorite ? 'orange' : 'neutral' }}">
                                    @if ($memory->is_favorite)
                                        <i class="bi bi-heart-fill"></i>
                                    @endif
                                    {{ $memory->typeLabel() }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif

        @if ($undated->isNotEmpty())
            <section class="undated-panel mt-4">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                    <div>
                        <h3>{{ __('ui.timeline.no_exact_date') }}</h3>
                        <p>{{ __('ui.timeline.no_exact_date_body') }}</p>
                    </div>
                    <span class="badge-soft neutral">{{ $undated->count() }} {{ __('ui.common.results') }}</span>
                </div>

                <div class="timeline-cards">
                    @foreach ($undated as $memory)
                        <a href="{{ route('memories.show', $memory) }}" class="timeline-memory" data-memory-type="{{ $memory->memory_type }}">
                            <span class="timeline-thumb"><i class="bi {{ $memory->typeIcon() }}"></i></span>                            <span class="timeline-copy">
                                <h3>{{ $memory->title }}</h3>
                                <p>{{ __('ui.timeline.captured_on', ['date' => $memory->displayDate()]) }}</p>
                            </span>
                            <span class="badge-soft neutral">{{ $memory->typeLabel() }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
