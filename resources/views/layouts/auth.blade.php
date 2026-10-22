<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body>
    <div class="auth-shell">
        <aside class="auth-art">
            <a href="{{ route('home') }}" class="brand">
                <span class="brand-mark"><i class="bi bi-journal-bookmark-fill"></i></span>
                <span class="brand-copy">
                    <span class="brand-name">{{ __('ui.app.name') }}</span>
                    <span class="brand-tag">{{ __('ui.app.tagline') }}</span>
                </span>
            </a>

            <div class="auth-art-copy">
                <h1>{{ __('ui.app.promise') }}</h1>
                <p>{{ __('ui.app.description') }}</p>

                <div class="auth-quote">
                    “{{ __('ui.landing.demo_answer') }}”
                </div>

                <div class="auth-art-cards">
                    <div class="auth-mini-memory">
                        <span class="auth-mini-icon"><i class="bi bi-receipt"></i></span>
                        <span class="auth-mini-copy">
                            <strong>iPhone 15 purchase receipt</strong>
                            <span>Star Tech, Dhanmondi · 12 Sep 2026</span>
                        </span>
                    </div>
                    <div class="auth-mini-memory">
                        <span class="auth-mini-icon"><i class="bi bi-tools"></i></span>
                        <span class="auth-mini-copy">
                            <strong>Walton fridge repair</strong>
                            <span>মেরামত · ১৮ জুলাই ২০২৫</span>
                        </span>
                    </div>
                    <div class="auth-mini-memory">
                        <span class="auth-mini-icon"><i class="bi bi-airplane"></i></span>
                        <span class="auth-mini-copy">
                            <strong>Padma Bridge day trip</strong>
                            <span>Family · 24 January 2026</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="auth-art-footer">
                <span class="privacy-line"><i class="bi bi-shield-lock"></i> {{ __('ui.common.private') }} by default</span>
                <a href="{{ route('privacy') }}">{{ __('ui.common.learn_more') }}</a>
            </div>
        </aside>

        <main class="auth-panel">
            <div class="auth-panel-top">
                <a href="{{ route('home') }}" class="brand">
                    <span class="brand-mark"><i class="bi bi-journal-bookmark-fill"></i></span>
                    <span class="brand-copy">
                        <span class="brand-name">{{ __('ui.app.name') }}</span>
                    </span>
                </a>

                <div class="dropdown">
                    <button type="button" class="btn btn-soft btn-sm" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-translate"></i>
                        {{ $interfaceLocale === 'bn' ? 'বাংলা' : 'English' }}
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
            </div>

            <div class="auth-form-wrap">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
