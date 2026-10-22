@extends('layouts.app')

@section('title', __('ui.search.title'))

@php
    $types = ['purchase', 'receipt', 'document', 'person', 'place', 'event', 'repair', 'item', 'task', 'idea', 'travel', 'note', 'meeting', 'warranty', 'medical', 'link'];
    $hasQuery = $query !== '';
    $confidenceClass = match ($result['confidence'] ?? 'low') {
        'high' => 'green',
        'medium' => 'orange',
        default => 'neutral',
    };
    $matchLabels = [
        'exact' => __('ui.search.exact'),
        'semantic' => __('ui.search.semantic'),
        'related' => __('ui.search.related'),
    ];
    $dateRangeLabel = ! empty($result['date_range'])
        ? (is_numeric($result['date_range']['label'])
            ? $result['date_range']['label']
            : __('ui.search.'.$result['date_range']['label']))
        : null;
@endphp

@section('content')
    <section class="search-hero">
        <span class="eyebrow">{{ __('ui.search.recall') }}</span>
        <h1>{{ __('ui.search.title') }}</h1>
        <p>{{ __('ui.search.subtitle') }}</p>

        <form method="GET" action="{{ route('search.index') }}" class="search-main-form" data-search-form>
            <div class="search-input-shell">
                <i class="bi bi-search"></i>
                <input
                    type="search"
                    name="q"
                    value="{{ $query }}"
                    placeholder="{{ __('ui.search.placeholder') }}"
                    aria-label="{{ __('ui.search.title') }}"
                    autofocus
                >
                <button type="submit" class="btn btn-accent">
                    <i class="bi bi-stars"></i>
                    {{ __('ui.search.ask') }}
                </button>
            </div>

            <div class="search-filters-row">
                <button type="button" class="filter-toggle" data-bs-toggle="collapse" data-bs-target="#searchFilters" aria-expanded="{{ $hasQuery ? 'false' : 'true' }}">
                    <i class="bi bi-sliders2"></i>
                    {{ __('ui.common.filters') }}
                </button>

                @if ($filters['type'] !== 'all')
                    <span class="chip chip-accent">{{ __('ui.types.'.$filters['type']) }}</span>
                @endif
                @if ($filters['date'] !== 'all')
                    <span class="chip chip-gold">{{ __('ui.search.'.$filters['date']) }}</span>
                @endif
                @if ($filters['language'] !== 'all')
                    <span class="chip chip-blue">{{ __('ui.languages.'.$filters['language']) }}</span>
                @endif
                @if ($filters['favorite'])
                    <span class="chip chip-sage"><i class="bi bi-heart-fill"></i> {{ __('ui.search.filter_favorite') }}</span>
                @endif
                @if ($filters['include_archived'])
                    <span class="chip"><i class="bi bi-archive"></i> {{ __('ui.search.filter_archived') }}</span>
                @endif
                @if ($hasQuery)
                    <a href="{{ route('search.index') }}" class="chip">
                        <i class="bi bi-x-lg"></i>
                        {{ __('ui.search.clear') }}
                    </a>
                @endif
            </div>

            <div class="collapse {{ $hasQuery ? '' : 'show' }}" id="searchFilters">
                <div class="filter-drawer">
                    <div>
                        <label class="form-label" for="filter-type">{{ __('ui.search.filter_type') }}</label>
                        <select id="filter-type" name="type" class="form-select">
                            <option value="all">{{ __('ui.search.any_type') }}</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ __('ui.types.'.$type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="filter-date">{{ __('ui.search.filter_date') }}</label>
                        <select id="filter-date" name="date" class="form-select">
                            <option value="all">{{ __('ui.search.any_date') }}</option>
                            <option value="last_week" @selected($filters['date'] === 'last_week')>{{ __('ui.search.last_week') }}</option>
                            <option value="last_month" @selected($filters['date'] === 'last_month')>{{ __('ui.search.last_month') }}</option>
                            <option value="last_year" @selected($filters['date'] === 'last_year')>{{ __('ui.search.last_year') }}</option>
                            <option value="this_year" @selected($filters['date'] === 'this_year')>{{ __('ui.search.this_year') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="filter-language">{{ __('ui.search.filter_language') }}</label>
                        <select id="filter-language" name="language" class="form-select">
                            <option value="all">{{ __('ui.search.any_language') }}</option>
                            <option value="en" @selected($filters['language'] === 'en')>{{ __('ui.languages.en') }}</option>
                            <option value="bn" @selected($filters['language'] === 'bn')>{{ __('ui.languages.bn') }}</option>
                        </select>
                    </div>
                    <div class="d-flex flex-column justify-content-end gap-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="favorite" value="1" id="filter-favorite" @checked($filters['favorite'])>
                            <label class="form-check-label" for="filter-favorite">{{ __('ui.search.filter_favorite') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="archived" value="1" id="filter-archived" @checked($filters['include_archived'])>
                            <label class="form-check-label" for="filter-archived">{{ __('ui.search.filter_archived') }}</label>
                        </div>
                    </div>
                    <div class="d-flex align-items-end">
                        <button type="submit" class="btn btn-ink filter-submit w-100">{{ __('ui.search.apply_filters') }}</button>
                    </div>
                </div>
            </div>

            @if (! $hasQuery)
                <div class="search-empty-suggestions">
                    @foreach (['suggestion_1', 'suggestion_2', 'suggestion_3', 'suggestion_4', 'suggestion_5'] as $suggestion)
                        <button type="button" class="suggestion-pill" data-search-suggestion="{{ __('ui.search.'.$suggestion) }}">
                            <i class="bi bi-stars"></i>
                            {{ __('ui.search.'.$suggestion) }}
                        </button>
                    @endforeach
                </div>
            @endif
        </form>
    </section>

    @if ($hasQuery && $result['too_short'])
        <div class="recall-empty">
            <span class="empty-icon"><i class="bi bi-backspace"></i></span>
            <h2>{{ __('ui.search.no_answer_title') }}</h2>
            <p>{{ __('ui.search.query_too_short') }}</p>
            <a href="{{ route('search.index') }}" class="btn btn-soft">{{ __('ui.search.clear') }}</a>
        </div>
    @elseif ($hasQuery)
        <div class="answer-layout">
            <div>
                @if ($result['has_answer'] && $result['answer'])
                    <article class="recall-answer">
                        <div class="recall-head">
                            <div class="recall-brand">
                                <span class="recall-mark"><i class="bi bi-stars"></i></span>
                                <div>
                                    <strong>{{ __('ui.search.recall') }}</strong>
                                    <span>{{ __('ui.search.evidence_based') }}</span>
                                </div>
                            </div>
                            <span class="badge-soft {{ $confidenceClass }}">
                                <i class="bi bi-bullseye"></i>
                                {{ __('ui.search.'.$result['confidence_label']) }}
                            </span>
                        </div>

                        <div class="recall-body">
                            <p class="recall-answer-text">{{ $result['answer'] }}</p>

                            <div class="recall-meta">
                                <span><i class="bi bi-collection"></i> {{ trans_choice('ui.search.source_count', count($result['sources']), [], $result['locale']) }}</span>
                                @if ($result['date_range'])
                                    <span><i class="bi bi-calendar3"></i> {{ $dateRangeLabel }}</span>
                                @endif
                                <span><i class="bi bi-shield-check"></i> {{ __('ui.search.answer_disclaimer') }}</span>
                            </div>
                        </div>
                    </article>
                @else
                    <div class="recall-empty">
                        <span class="empty-icon"><i class="bi bi-search"></i></span>
                        <h2>{{ __('ui.search.no_answer_title') }}</h2>
                        <p>{{ __('ui.search.no_answer_body') }}</p>
                        <div class="search-empty-suggestions justify-content-center">
                            @foreach (['suggestion_1', 'suggestion_3', 'suggestion_5'] as $suggestion)
                                <button type="button" class="suggestion-pill" data-search-suggestion="{{ __('ui.search.'.$suggestion) }}">
                                    <i class="bi bi-stars"></i>
                                    {{ __('ui.search.'.$suggestion) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($result['sources']))
                    <section class="sources-section">
                        <div class="sources-head">
                            <h2>{{ __('ui.common.evidence') }}</h2>
                            <span>{{ __('ui.memory.evidence_note') }}</span>
                        </div>

                        <div class="source-grid">
                            @foreach ($result['sources'] as $source)
                                @php($sourceMemory = $source['memory'])
                                <a href="{{ route('memories.show', $sourceMemory) }}" class="source-card" data-memory-type="{{ $sourceMemory->memory_type }}">
                                    <div class="source-card-top">
                                        <span class="match-badge {{ $source['match_type'] }}">{{ $matchLabels[$source['match_type']] ?? __('ui.search.related') }}</span>
                                        <span class="memory-date">{{ $source['date'] }}</span>
                                    </div>
                                    <h3>{{ $sourceMemory->title }}</h3>
                                    <p class="source-excerpt">{{ $source['excerpt'] }}</p>
                                    <div class="source-foot">
                                        <i class="bi {{ $sourceMemory->typeIcon() }}"></i>
                                        {{ $sourceMemory->typeLabel() }}
                                        <span>· {{ $sourceMemory->languageName() }}</span>
                                        @if (($source['vector_match'] ?? false) && ($source['similarity'] ?? 0) > 0)
                                            <span>· {{ __('ui.search.similarity') }} {{ number_format($source['similarity'] * 100) }}%</span>
                                        @endif
                                        <i class="bi bi-arrow-up-right"></i>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if (! empty($result['results']))
                    <section class="search-results-section">
                        <div class="search-results-head">
                            <div>
                                <h2>{{ __('ui.search.results_title') }}</h2>
                                <p>{{ __('ui.search.results_count', ['count' => $result['total']]) }}</p>
                            </div>
                            <a href="{{ route('memories.index', ['q' => $query]) }}" class="panel-link">{{ __('ui.nav.library') }} <i class="bi bi-arrow-right"></i></a>
                        </div>

                        <div class="result-list">
                            @foreach ($result['results'] as $item)
                                @php($resultMemory = $item['memory'])
                                <a href="{{ route('memories.show', $resultMemory) }}" class="result-card" data-memory-type="{{ $resultMemory->memory_type }}">
                                    <span class="result-thumb"><i class="bi {{ $resultMemory->typeIcon() }}"></i></span>
                                    <span class="result-copy">
                                        <h3>{{ $resultMemory->title }}</h3>
                                        <p>
                                            {{ $resultMemory->typeLabel() }} · {{ $resultMemory->displayDate() }} · {{ $resultMemory->languageName() }}
                                            @if (($item['vector_match'] ?? false) && ($item['similarity'] ?? 0) > 0)
                                                · {{ __('ui.search.similarity') }} {{ number_format($item['similarity'] * 100) }}%
                                            @endif
                                        </p>
                                    </span>
                                    <span class="match-badge {{ $item['match_type'] }}">{{ $matchLabels[$item['match_type']] ?? __('ui.search.related') }}</span>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <aside class="search-side-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="side-title mb-0">{{ __('ui.search.recent_searches') }}</span>
                    <i class="bi bi-clock-history text-muted-soft"></i>
                </div>

                @if ($recentSearches->isEmpty())
                    <p class="text-muted-soft small mb-0">{{ __('ui.search.empty_body') }}</p>
                @else
                    <div class="recent-search-list">
                        @foreach ($recentSearches as $history)
                            <a href="{{ route('search.index', ['q' => $history->query]) }}" class="recent-search-item">
                                <i class="bi bi-search"></i>
                                <span class="text-truncate">{{ $history->query }}</span>
                                <span class="ms-auto">{{ $history->results_count }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <hr class="my-3" style="border-color: var(--line);">

                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-shield-lock text-accent"></i>
                    <strong class="small">{{ __('ui.search.answer_disclaimer') }}</strong>
                </div>
                <p class="text-muted-soft small mb-0">{{ __('ui.memory.ai_disclosure') }}</p>
            </aside>
        </div>
    @else
        <div class="recall-empty">
            <span class="empty-icon"><i class="bi bi-chat-square-quote"></i></span>
            <h2>{{ __('ui.search.empty_title') }}</h2>
            <p>{{ __('ui.search.empty_body') }}</p>

            <div class="search-empty-suggestions justify-content-center">
                @foreach (['suggestion_1', 'suggestion_2', 'suggestion_3', 'suggestion_4', 'suggestion_5'] as $suggestion)
                    <button type="button" class="suggestion-pill" data-search-suggestion="{{ __('ui.search.'.$suggestion) }}">
                        <i class="bi bi-stars"></i>
                        {{ __('ui.search.'.$suggestion) }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($recentSearches->isNotEmpty())
            <section class="search-results-section">
                <div class="search-results-head">
                    <h2>{{ __('ui.search.recent_searches') }}</h2>
                    <p>{{ __('ui.search.answer_disclaimer') }}</p>
                </div>
                <div class="search-side-card">
                    <div class="recent-search-list">
                        @foreach ($recentSearches as $history)
                            <a href="{{ route('search.index', ['q' => $history->query]) }}" class="recent-search-item">
                                <i class="bi bi-clock-history"></i>
                                <span class="text-truncate">{{ $history->query }}</span>
                                <span class="ms-auto">{{ trans_choice('ui.common.result', $history->results_count) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @endif
@endsection
