@extends('layouts.app')

@php
    $isFavorites = $mode === 'favorites';
    $isArchive = $mode === 'archive';
    $routeName = match ($mode) {
        'favorites' => 'memories.favorites',
        'archive' => 'memories.archived',
        default => 'memories.index',
    };
    $pageTitle = $isArchive ? __('ui.library.archive_title') : ($isFavorites ? __('ui.library.favorites_title') : __('ui.library.title'));
    $pageSubtitle = $isArchive ? __('ui.library.archive_subtitle') : ($isFavorites ? __('ui.library.favorites_subtitle') : __('ui.library.subtitle'));
    $listView = request('view') === 'list';
    $types = ['purchase', 'receipt', 'document', 'person', 'place', 'event', 'repair', 'item', 'task', 'idea', 'travel', 'note', 'meeting', 'warranty', 'medical', 'link'];
    $filterUrl = fn (array $overrides = []) => route($routeName, array_merge(request()->except('page'), $overrides));
@endphp

@section('title', $pageTitle)

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">{{ $isArchive ? __('ui.nav.archive') : ($isFavorites ? __('ui.nav.favorites') : __('ui.nav.library')) }}</span>
            <h1>{{ $pageTitle }}</h1>
            <p>{{ $pageSubtitle }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('capture.create') }}" class="btn btn-accent">
                <i class="bi bi-plus-lg"></i>
                {{ __('ui.nav.capture') }}
            </a>
        </div>
    </div>

    <div class="library-layout">
        <aside class="library-side">
            <div class="panel filter-panel">
                <div class="filter-panel-title">
                    <span>{{ __('ui.library.all_memories') }}</span>
                    <span>{{ $totalForMode }}</span>
                </div>
                <div class="filter-list">
                    <a href="{{ $filterUrl(['type' => 'all']) }}" class="filter-link {{ request('type', 'all') === 'all' ? 'active' : '' }}">
                        <i class="bi bi-collection"></i>
                        {{ __('ui.library.all_memories') }}
                        <span class="count">{{ $totalForMode }}</span>
                    </a>
                    @foreach ($typeCounts as $typeCount)
                        <a href="{{ $filterUrl(['type' => $typeCount->memory_type]) }}" class="filter-link {{ request('type') === $typeCount->memory_type ? 'active' : '' }}">
                            <i class="bi {{ \App\Models\Memory::iconForType($typeCount->memory_type) }}"></i>
                            {{ __('ui.types.'.$typeCount->memory_type) }}
                            <span class="count">{{ $typeCount->total }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="panel filter-panel">
                <div class="filter-panel-title">
                    <span>{{ __('ui.library.collections') }}</span>
                    <i class="bi bi-folder2"></i>
                </div>
                <div class="filter-list">
                    @forelse ($collections as $collection)
                        <a href="{{ $filterUrl(['collection' => $collection->id]) }}" class="filter-link {{ (string) request('collection') === (string) $collection->id ? 'active' : '' }}">
                            <span class="collection-dot" style="background: {{ $collection->color }}"></span>
                            <span class="text-truncate">{{ $collection->name }}</span>
                            <span class="count">{{ $collection->memories_count }}</span>
                        </a>
                    @empty
                        <p class="text-muted-soft small mb-0 px-2">{{ __('ui.dashboard.collection') }} — {{ __('ui.common.not_set') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="panel filter-panel">
                <div class="filter-panel-title">
                    <span>{{ __('ui.common.more') }}</span>
                </div>
                <div class="filter-list">
                    <a href="{{ route('memories.index') }}" class="filter-link {{ $mode === 'library' ? 'active' : '' }}">
                        <i class="bi bi-collection"></i> {{ __('ui.nav.library') }}
                    </a>
                    <a href="{{ route('memories.favorites') }}" class="filter-link {{ $isFavorites ? 'active' : '' }}">
                        <i class="bi bi-heart"></i> {{ __('ui.nav.favorites') }}
                    </a>
                    <a href="{{ route('memories.archived') }}" class="filter-link {{ $isArchive ? 'active' : '' }}">
                        <i class="bi bi-archive"></i> {{ __('ui.nav.archive') }}
                    </a>
                </div>
            </div>
        </aside>

        <div>
            <div class="library-toolbar">
                <form method="GET" action="{{ route($routeName) }}" class="library-search">
                    @if (request('type'))
                        <input type="hidden" name="type" value="{{ request('type') }}">
                    @endif
                    @if (request('collection'))
                        <input type="hidden" name="collection" value="{{ request('collection') }}">
                    @endif
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('ui.library.search_placeholder') }}" aria-label="{{ __('ui.common.search') }}">
                    @if ($search !== '')
                        <a href="{{ $filterUrl(['q' => null]) }}" class="chip"><i class="bi bi-x-lg"></i></a>
                    @endif
                </form>

                <div class="library-controls">
                    <form method="GET" action="{{ route($routeName) }}">
                        @foreach (request()->except(['sort', 'page']) as $key => $value)
                            @if (is_scalar($value) && $value !== '')
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <select name="sort" class="form-select" data-auto-submit aria-label="{{ __('ui.library.sort') }}">
                            <option value="newest" @selected($sort === 'newest')>{{ __('ui.library.sort_newest') }}</option>
                            <option value="oldest" @selected($sort === 'oldest')>{{ __('ui.library.sort_oldest') }}</option>
                            <option value="event" @selected($sort === 'event')>{{ __('ui.library.sort_event') }}</option>
                            <option value="az" @selected($sort === 'az')>{{ __('ui.library.sort_az') }}</option>
                        </select>
                    </form>

                    <div class="view-toggle">
                        <a href="{{ $filterUrl(['view' => null]) }}" class="{{ ! $listView ? 'active' : '' }}" title="{{ __('ui.library.view_grid') }}" aria-label="{{ __('ui.library.view_grid') }}">
                            <i class="bi bi-grid"></i>
                        </a>
                        <a href="{{ $filterUrl(['view' => 'list']) }}" class="{{ $listView ? 'active' : '' }}" title="{{ __('ui.library.view_list') }}" aria-label="{{ __('ui.library.view_list') }}">
                            <i class="bi bi-list-ul"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="library-count">
                <strong>{{ __('ui.library.showing', ['count' => $memories->count(), 'total' => $memories->total()]) }}</strong>
                @if ($search !== '')
                    · “{{ $search }}”
                @endif
            </div>

            @if ($memories->isEmpty())
                <div class="panel">
                    <x-empty-state
                        icon="bi-collection"
                        :title="__('ui.library.empty_title')"
                        :body="__('ui.library.empty_body')"
                        :action-route="route('capture.create')"
                        :action-label="__('ui.dashboard.add_first')"
                    />
                </div>
            @else
                <div class="memory-grid {{ $listView ? 'list-view' : 'three' }}">
                    @foreach ($memories as $memory)
                        <x-memory-card :memory="$memory" :list-view="$listView" />
                    @endforeach
                </div>

                <div class="pagination-wrap">
                    {{ $memories->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
