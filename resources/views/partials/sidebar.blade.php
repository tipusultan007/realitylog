@php
    $storageUsed = $navStorageBytes ?? 0;
    $storageLimit = 5 * 1024 * 1024 * 1024;
    $storagePercent = max(2, min(100, (int) round(($storageUsed / $storageLimit) * 100)));
    $storageLabel = $storageUsed >= 1048576
        ? number_format($storageUsed / 1048576, 1).' MB'
        : ($storageUsed >= 1024 ? number_format($storageUsed / 1024, 0).' KB' : $storageUsed.' B');
@endphp

<aside class="sidebar" data-sidebar>
    <a href="{{ route('dashboard') }}" class="brand">
        <span class="brand-mark"><i class="bi bi-journal-bookmark-fill"></i></span>
        <span class="brand-copy">
            <span class="brand-name">{{ __('ui.app.name') }}</span>
            <span class="brand-tag">{{ __('ui.app.tagline') }}</span>
        </span>
    </a>

    <a href="{{ route('capture.create') }}" class="sidebar-capture">
        <i class="bi bi-plus-lg"></i>
        {{ __('ui.nav.capture') }}
        <kbd>C</kbd>
    </a>

    <div class="sidebar-scroll">
        <div class="sidebar-group">
            <div class="sidebar-label">{{ __('ui.nav.workspace') }}</div>
            <nav class="sidebar-nav" aria-label="{{ __('ui.nav.main') }}">
                <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-house-door"></i>
                    {{ __('ui.nav.home') }}
                </a>
                <a href="{{ route('search.index') }}" class="sidebar-link {{ request()->routeIs('search.*') ? 'active' : '' }}">
                    <i class="bi bi-search"></i>
                    {{ __('ui.nav.search') }}
                </a>
                <a href="{{ route('timeline.index') }}" class="sidebar-link {{ request()->routeIs('timeline.*') ? 'active' : '' }}">
                    <i class="bi bi-calendar3"></i>
                    {{ __('ui.nav.timeline') }}
                </a>
                <a href="{{ route('memories.index') }}" class="sidebar-link {{ request()->routeIs('memories.index') || request()->routeIs('memories.show') || request()->routeIs('memories.edit') ? 'active' : '' }}">
                    <i class="bi bi-collection"></i>
                    {{ __('ui.nav.library') }}
                    <span class="sidebar-count">{{ $navMemoryCount ?? 0 }}</span>
                </a>
                <a href="{{ route('memories.favorites') }}" class="sidebar-link {{ request()->routeIs('memories.favorites') ? 'active' : '' }}">
                    <i class="bi bi-heart"></i>
                    {{ __('ui.nav.favorites') }}
                </a>
                <a href="{{ route('memories.archived') }}" class="sidebar-link {{ request()->routeIs('memories.archived') ? 'active' : '' }}">
                    <i class="bi bi-archive"></i>
                    {{ __('ui.nav.archive') }}
                </a>
                <a href="{{ route('reminders.index') }}" class="sidebar-link {{ request()->routeIs('reminders.*') ? 'active' : '' }}">
                    <i class="bi bi-bell"></i>
                    {{ __('ui.nav.reminders') }}
                    @if (($navReminderCount ?? 0) > 0)
                        <span class="sidebar-count is-accent">{{ $navReminderCount }}</span>
                    @endif
                </a>
            </nav>
        </div>

        <div class="sidebar-group">
            <div class="sidebar-label">{{ __('ui.nav.settings') }}</div>
            <nav class="sidebar-nav">
                <a href="{{ route('settings.edit') }}" class="sidebar-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <i class="bi bi-sliders2"></i>
                    {{ __('ui.nav.settings') }}
                </a>
                <a href="{{ route('help') }}" class="sidebar-link {{ request()->routeIs('help') ? 'active' : '' }}">
                    <i class="bi bi-life-preserver"></i>
                    {{ __('ui.nav.help') }}
                </a>
            </nav>
        </div>
    </div>

    <div class="sidebar-storage">
        <div class="sidebar-storage-head">
            <span>{{ __('ui.dashboard.storage') }}</span>
            <span>{{ $storageLabel }} / 5 GB</span>
        </div>
        <div class="storage-track"><span style="width: {{ $storagePercent }}%"></span></div>
        <div class="sidebar-storage-foot">
            <span>{{ __('ui.dashboard.storage_used') }}</span>
            <a href="{{ route('settings.edit') }}">{{ __('ui.dashboard.manage_plan') }}</a>
        </div>
    </div>

    <div class="sidebar-user">
        <span class="avatar">{{ $currentUser?->initials() ?? 'RL' }}</span>
        <span class="sidebar-user-copy">
            <strong>{{ $currentUser?->name ?? __('ui.app.name') }}</strong>
            <span>{{ __('ui.common.private') }} · {{ strtoupper($interfaceLocale ?? 'en') }}</span>
        </span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" title="{{ __('ui.nav.logout') }}" aria-label="{{ __('ui.nav.logout') }}">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
</aside>
