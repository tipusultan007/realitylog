<nav class="mobile-nav" aria-label="{{ __('ui.nav.main') }}">
    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <i class="bi bi-house-door"></i>
        <span>{{ __('ui.nav.home') }}</span>
    </a>
    <a href="{{ route('search.index') }}" class="{{ request()->routeIs('search.*') ? 'active' : '' }}">
        <i class="bi bi-search"></i>
        <span>{{ __('ui.nav.search') }}</span>
    </a>
    <a href="{{ route('capture.create') }}" class="mobile-capture" aria-label="{{ __('ui.nav.capture') }}">
        <i class="bi bi-plus-lg"></i>
        <span>{{ __('ui.nav.capture') }}</span>
    </a>
    <a href="{{ route('memories.index') }}" class="{{ request()->routeIs('memories.*') ? 'active' : '' }}">
        <i class="bi bi-collection"></i>
        <span>{{ __('ui.nav.library') }}</span>
    </a>
    <a href="{{ route('settings.edit') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">
        <i class="bi bi-sliders2"></i>
        <span>{{ __('ui.nav.settings') }}</span>
    </a>
</nav>
