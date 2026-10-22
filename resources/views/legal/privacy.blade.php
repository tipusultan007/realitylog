<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="legal-page">
    <div class="container-narrow">
        <nav class="legal-nav">
            <a href="{{ route('home') }}" class="brand">
                <span class="brand-mark"><i class="bi bi-journal-bookmark-fill"></i></span>
                <span class="brand-copy">
                    <span class="brand-name">{{ __('ui.app.name') }}</span>
                    <span class="brand-tag">{{ __('ui.app.tagline') }}</span>
                </span>
            </a>

            <div class="d-flex align-items-center gap-2">
                <div class="lang-menu d-flex gap-1">
                    <form method="POST" action="{{ route('locale.update', 'en') }}">
                        @csrf
                        <button type="submit" class="btn btn-soft btn-sm {{ $interfaceLocale === 'en' ? 'active' : '' }}">EN</button>
                    </form>
                    <form method="POST" action="{{ route('locale.update', 'bn') }}">
                        @csrf
                        <button type="submit" class="btn btn-soft btn-sm {{ $interfaceLocale === 'bn' ? 'active' : '' }}">বাংলা</button>
                    </form>
                </div>

                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-accent btn-sm">{{ __('ui.nav.home') }}</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-accent btn-sm">{{ __('ui.auth.login') }}</a>
                @endauth
            </div>
        </nav>

        <main class="legal-content">
            <span class="eyebrow">{{ __('ui.common.private') }}</span>
            <h1>{{ __('ui.privacy.title') }}</h1>
            <p class="lede">{{ __('ui.privacy.intro') }}</p>

            <section class="legal-card">
                <h2>{{ __('ui.privacy.private_title') }}</h2>
                <p>{{ __('ui.privacy.private_body') }}</p>
            </section>

            <section class="legal-card">
                <h2>{{ __('ui.privacy.processing_title') }}</h2>
                <p>{{ __('ui.privacy.processing_body') }}</p>
            </section>

            <section class="legal-card">
                <h2>{{ __('ui.privacy.evidence_title') }}</h2>
                <p>{{ __('ui.privacy.evidence_body') }}</p>
            </section>

            <section class="legal-card">
                <h2>{{ __('ui.privacy.control_title') }}</h2>
                <p>{{ __('ui.privacy.control_body') }}</p>
            </section>

            <section class="legal-card">
                <h2>{{ __('ui.help.contact') }}</h2>
                <p>
                    {{ __('ui.privacy.contact') }}
                    <a href="mailto:privacy@realitylog.app" class="text-accent fw-bold">privacy@realitylog.app</a>
                </p>
            </section>

            <div class="d-flex flex-wrap align-items-center gap-2 mt-4">
                <a href="{{ route('home') }}" class="btn btn-soft">
                    <i class="bi bi-arrow-left"></i>
                    {{ __('ui.common.back') }}
                </a>
                @guest
                    <a href="{{ route('register') }}" class="btn btn-accent">
                        {{ __('ui.landing.start_free') }}
                        <i class="bi bi-arrow-right"></i>
                    </a>
                @endguest
            </div>
        </main>

        <footer class="mt-5 pt-3" style="border-top: 1px solid var(--line); color: var(--muted); font-size: 0.72rem;">
            {{ __('ui.landing.footer_note') }}
        </footer>
    </div>
</body>
</html>
