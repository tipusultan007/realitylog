@extends('layouts.app')

@section('title', __('ui.nav.home'))

@section('content')
    <div class="dashboard-head">
        <div>
            <span class="dashboard-date">{{ now()->translatedFormat('l, j F Y') }}</span>
            <h1>{{ $greeting }}, {{ \Illuminate\Support\Str::before($currentUser->name, ' ') }}.</h1>
            <p>{{ __('ui.dashboard.subtitle') }}</p>
        </div>
        <div class="dashboard-head-actions">
            <a href="{{ route('capture.create') }}" class="btn btn-accent">
                <i class="bi bi-plus-lg"></i>
                {{ __('ui.nav.capture') }}
            </a>
        </div>
    </div>

    @if ($processingCount > 0)
        <div class="processing-card">
            <i class="bi bi-stars"></i>
            <span>{{ __('ui.dashboard.processing_note') }}</span>
            <a href="{{ route('memories.index', ['type' => 'document']) }}" class="ms-auto fw-bold">{{ __('ui.common.view') }}</a>
        </div>
    @endif

    <section class="dashboard-search">
        <span class="hero-orbit" aria-hidden="true"></span>
        <div class="dashboard-search-inner">
            <span class="eyebrow">{{ __('ui.dashboard.search_label') }}</span>
            <h2>{{ __('ui.app.promise') }}</h2>

            <form method="GET" action="{{ route('search.index') }}" class="hero-search-form" data-search-form>
                <div class="hero-search-box">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" placeholder="{{ __('ui.dashboard.search_placeholder') }}" aria-label="{{ __('ui.common.search') }}">
                    <button type="submit" class="btn btn-accent">{{ __('ui.search.ask') }}</button>
                </div>
            </form>

            <div class="dashboard-suggestions">
                <button type="button" data-search-suggestion="{{ __('ui.dashboard.suggestion_1') }}">{{ __('ui.dashboard.suggestion_1') }}</button>
                <button type="button" data-search-suggestion="{{ __('ui.dashboard.suggestion_2') }}">{{ __('ui.dashboard.suggestion_2') }}</button>
                <button type="button" data-search-suggestion="{{ __('ui.dashboard.suggestion_3') }}">{{ __('ui.dashboard.suggestion_3') }}</button>
                <button type="button" data-search-suggestion="{{ __('ui.dashboard.suggestion_4') }}">{{ __('ui.dashboard.suggestion_4') }}</button>
            </div>
        </div>
    </section>

    <div class="quick-capture">
        <a href="{{ route('capture.create', ['type' => 'text']) }}" class="capture-tile">
            <span class="capture-tile-icon"><i class="bi bi-pencil"></i></span>
            <span>{{ __('ui.dashboard.capture_note') }}</span>
        </a>
        <a href="{{ route('capture.create', ['type' => 'image']) }}" class="capture-tile">
            <span class="capture-tile-icon"><i class="bi bi-camera"></i></span>
            <span>{{ __('ui.dashboard.capture_photo') }}</span>
        </a>
        <a href="{{ route('capture.create', ['type' => 'document']) }}" class="capture-tile">
            <span class="capture-tile-icon"><i class="bi bi-file-earmark-arrow-up"></i></span>
            <span>{{ __('ui.dashboard.capture_document') }}</span>
        </a>
        <a href="{{ route('capture.create', ['type' => 'voice']) }}" class="capture-tile">
            <span class="capture-tile-icon"><i class="bi bi-mic"></i></span>
            <span>{{ __('ui.dashboard.capture_voice') }}</span>
        </a>
        <a href="{{ route('capture.create', ['type' => 'link']) }}" class="capture-tile">
            <span class="capture-tile-icon"><i class="bi bi-link-45deg"></i></span>
            <span>{{ __('ui.dashboard.capture_link') }}</span>
        </a>
    </div>

    <div class="stats-strip">
        <div class="stat-card">
            <span class="stat-icon"><i class="bi bi-collection"></i></span>
            <div>
                <div class="stat-value">{{ $totalMemories }}</div>
                <div class="stat-label">{{ __('ui.dashboard.stats_memories') }}</div>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><i class="bi bi-file-earmark-text"></i></span>
            <div>
                <div class="stat-value">{{ $totalDocuments }}</div>
                <div class="stat-label">{{ __('ui.dashboard.stats_documents') }}</div>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><i class="bi bi-bell"></i></span>
            <div>
                <div class="stat-value">{{ $reminderCount }}</div>
                <div class="stat-label">{{ __('ui.dashboard.stats_reminders') }}</div>
            </div>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><i class="bi bi-heart"></i></span>
            <div>
                <div class="stat-value">{{ $favoriteCount }}</div>
                <div class="stat-label">{{ __('ui.dashboard.stats_favorites') }}</div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-main">
            <section>
                <div class="section-head">
                    <div>
                        <h2>{{ __('ui.dashboard.recent_memories') }}</h2>
                        <p>{{ __('ui.dashboard.recent_memories_sub') }}</p>
                    </div>
                    <a href="{{ route('memories.index') }}" class="panel-link">{{ __('ui.common.show_all') }} <i class="bi bi-arrow-right"></i></a>
                </div>

                @if ($recentMemories->isEmpty())
                    <x-empty-state
                        icon="bi-journal-plus"
                        :title="__('ui.dashboard.no_memories_title')"
                        :body="__('ui.dashboard.no_memories_body')"
                        :action-route="route('capture.create')"
                        :action-label="__('ui.dashboard.add_first')"
                    />
                @else
                    <div class="memory-grid">
                        @foreach ($recentMemories->take(4) as $memory)
                            <x-memory-card :memory="$memory" />
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <div class="section-head">
                    <div>
                        <h2>{{ __('ui.dashboard.timeline_today') }}</h2>
                        <p>{{ now()->translatedFormat('j F') }}</p>
                    </div>
                    <a href="{{ route('timeline.index', ['month' => now()->format('Y-m')]) }}" class="panel-link">{{ __('ui.nav.timeline') }} <i class="bi bi-arrow-right"></i></a>
                </div>

                @if ($onThisDay->isEmpty())
                    <div class="panel panel-pad">
                        <div class="d-flex align-items-center gap-3">
                            <span class="empty-icon mb-0" style="width: 52px; height: 52px; font-size: 1.05rem;"><i class="bi bi-calendar-heart"></i></span>
                            <p class="mb-0 text-muted-soft">{{ __('ui.dashboard.timeline_today_empty') }}</p>
                        </div>
                    </div>
                @else
                    <div class="result-list">
                        @foreach ($onThisDay as $memory)
                            <a href="{{ route('memories.show', $memory) }}" class="timeline-memory" data-memory-type="{{ $memory->memory_type }}">
                                <span class="timeline-thumb"><i class="bi bi-calendar-heart"></i></span>
                                <span class="timeline-copy">
                                    <h3>{{ $memory->title }}</h3>
                                    <p>{{ $memory->displayDateLong() }} · {{ $memory->typeLabel() }}</p>
                                </span>
                                <span class="badge-soft orange">{{ $memory->displayDate() }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <aside class="dashboard-side">
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('ui.dashboard.upcoming') }}</h2>
                        <p class="panel-subtitle">{{ __('ui.dashboard.upcoming_sub') }}</p>
                    </div>
                    <a href="{{ route('reminders.index') }}" class="panel-link">{{ __('ui.common.show_all') }}</a>
                </div>
                <div class="panel-body">
                    @if ($upcomingReminders->isEmpty())
                        <p class="text-muted-soft small mb-0">{{ __('ui.reminders.empty_upcoming') }}</p>
                    @else
                        <div class="reminder-list">
                            @foreach ($upcomingReminders as $reminder)
                                @include('partials.reminder-row', ['reminder' => $reminder])
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('ui.dashboard.memory_pulse') }}</h2>
                        <p class="panel-subtitle">{{ __('ui.dashboard.memory_pulse_sub') }}</p>
                    </div>
                </div>
                <div class="panel-body">
                    @if ($typeBreakdown->isEmpty())
                        <p class="text-muted-soft small mb-0">{{ __('ui.dashboard.no_memories_body') }}</p>
                    @else
                        <div class="pulse-list">
                            @foreach ($typeBreakdown as $type)
                                <div class="pulse-row">
                                    <div class="pulse-label">
                                        {{ __('ui.types.'.$type->memory_type) }}
                                        <span>{{ $type->total }}</span>
                                    </div>
                                    <div class="pulse-track">
                                        <span style="width: {{ max(8, (int) round(($type->total / max($typeBreakdown->max('total'), 1)) * 100)) }}%"></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            @if ($recentlyViewed->isNotEmpty())
                <section class="panel">
                    <div class="panel-head">
                        <h2 class="panel-title">{{ __('ui.dashboard.recently_viewed') }}</h2>
                    </div>
                    <div class="panel-body">
                        <div class="recent-view-list">
                            @foreach ($recentlyViewed as $memory)
                                <a href="{{ route('memories.show', $memory) }}" class="recent-view-item">
                                    <span class="recent-view-thumb"><i class="bi bi-journal-text"></i></span>
                                    <span class="recent-view-copy">
                                        <strong>{{ $memory->title }}</strong>
                                        <span>{{ $memory->displayDate() }} · {{ $memory->typeLabel() }}</span>
                                    </span>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            @if ($recentSearches->isNotEmpty())
                <section class="panel">
                    <div class="panel-head">
                        <h2 class="panel-title">{{ __('ui.search.recent_searches') }}</h2>
                    </div>
                    <div class="panel-body">
                        <div class="recent-search-list">
                            @foreach ($recentSearches as $history)
                                <a href="{{ route('search.index', ['q' => $history->query]) }}" class="recent-search-item">
                                    <i class="bi bi-clock-history"></i>
                                    <span class="text-truncate">{{ $history->query }}</span>
                                    <span class="ms-auto small">{{ $history->results_count }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif
        </aside>
    </div>
@endsection
