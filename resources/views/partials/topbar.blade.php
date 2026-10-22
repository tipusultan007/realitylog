@php
    $displayLocale = $interfaceLocale === 'bn' ? 'বাংলা' : 'EN';
    $context = match (true) {
        request()->routeIs('dashboard') => __('ui.nav.home'),
        request()->routeIs('capture.*') => __('ui.nav.capture'),
        request()->routeIs('search.*') => __('ui.nav.search'),
        request()->routeIs('timeline.*') => __('ui.nav.timeline'),
        request()->routeIs('memories.favorites') => __('ui.nav.favorites'),
        request()->routeIs('memories.archived') => __('ui.nav.archive'),
        request()->routeIs('memories.*') => __('ui.nav.library'),
        request()->routeIs('reminders.*') => __('ui.nav.reminders'),
        request()->routeIs('settings.*') => __('ui.nav.settings'),
        request()->routeIs('help') => __('ui.nav.help'),
        default => __('ui.app.name'),
    };
@endphp

<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="btn-icon mobile-menu-btn" data-sidebar-open aria-label="{{ __('ui.nav.menu') }}">
            <i class="bi bi-list"></i>
        </button>
        <span class="topbar-context">{{ __('ui.common.private') }} <strong>{{ $context }}</strong></span>
    </div>

    <form method="GET" action="{{ route('search.index') }}" class="topbar-search" role="search">
        <i class="bi bi-search"></i>
        <input
            type="search"
            name="q"
            value="{{ request()->routeIs('search.*') ? request('q') : '' }}"
            placeholder="{{ __('ui.common.search_placeholder') }}"
            data-global-search
            aria-label="{{ __('ui.common.search') }}"
        >
        <kbd>/</kbd>
    </form>

    <div class="topbar-actions">
        <button type="button" class="btn-icon theme-toggle" data-theme-toggle aria-label="{{ __('ui.common.theme') }}">
            <i class="bi bi-moon-stars-fill"></i>
            <i class="bi bi-sun-fill"></i>
        </button>

        <div class="dropdown">
            <button type="button" class="btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('ui.common.language') }}">
                <i class="bi bi-translate"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end lang-menu">
                <form method="POST" action="{{ route('locale.update', 'en') }}">
                    @csrf
                    <button type="submit" class="{{ $interfaceLocale === 'en' ? 'active' : '' }}">
                        <i class="bi bi-globe2"></i> English <span class="lang-code">EN</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('locale.update', 'bn') }}">
                    @csrf
                    <button type="submit" class="{{ $interfaceLocale === 'bn' ? 'active' : '' }}">
                        <i class="bi bi-globe2"></i> বাংলা <span class="lang-code">BN</span>
                    </button>
                </form>
            </div>
        </div>

        <a href="{{ route('reminders.index') }}" class="btn-icon position-relative" aria-label="{{ __('ui.nav.reminders') }}">
            <i class="bi bi-bell"></i>
            @if (($navReminderCount ?? 0) > 0)
                <span class="notification-dot"></span>
            @endif
        </a>

        <a href="{{ route('settings.edit') }}" class="avatar topbar-avatar" aria-label="{{ __('ui.nav.settings') }}">
            {{ $currentUser?->initials() ?? 'RL' }}
        </a>
    </div>
</header>
