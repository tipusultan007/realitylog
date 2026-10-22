<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="landing">
    <header class="landing-hero">
        <div class="container-wide">
            <nav class="landing-nav">
                <a href="{{ route('home') }}" class="brand">
                    <span class="brand-mark"><i class="bi bi-journal-bookmark-fill"></i></span>
                    <span class="brand-copy">
                        <span class="brand-name">{{ __('ui.app.name') }}</span>
                        <span class="brand-tag">{{ __('ui.app.tagline') }}</span>
                    </span>
                </a>

                <div class="landing-nav-links">
                    <a href="#how">{{ __('ui.landing.how_title') }}</a>
                    <a href="#bilingual">{{ __('ui.landing.bilingual_eyebrow') }}</a>
                    <a href="#privacy">{{ __('ui.landing.privacy_eyebrow') }}</a>
                    <a href="{{ route('privacy') }}">{{ __('ui.common.privacy') }}</a>
                </div>

                <div class="landing-nav-actions">
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

                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-accent btn-sm">{{ __('ui.nav.home') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-soft btn-sm">{{ __('ui.auth.login') }}</a>
                        <a href="{{ route('register') }}" class="btn btn-accent btn-sm">{{ __('ui.landing.start_free') }}</a>
                    @endauth
                </div>
            </nav>

            <div class="hero-grid">
                <div class="hero-copy">
                    <span class="eyebrow">{{ __('ui.landing.eyebrow') }}</span>
                    <h1>
                        {{ __('ui.landing.hero_title_1') }}<br>
                        <em>{{ __('ui.landing.hero_title_2') }}</em>
                    </h1>
                    <p>{{ __('ui.landing.hero_body') }}</p>

                    <div class="hero-actions">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-accent btn-lg">{{ __('ui.nav.home') }} <i class="bi bi-arrow-right"></i></a>
                        @else
                            <a href="{{ route('register') }}" class="btn btn-accent btn-lg">{{ __('ui.landing.start_free') }} <i class="bi bi-arrow-right"></i></a>
                        @endauth
                        <a href="#how" class="btn btn-soft btn-lg">{{ __('ui.landing.see_how') }}</a>
                    </div>

                    <div class="hero-trust">
                        <i class="bi bi-shield-lock"></i>
                        {{ __('ui.landing.trust') }}
                    </div>
                </div>

                <div class="hero-visual">
                    <span class="floating-tag tag-one"><i class="bi bi-lightning-charge"></i> {{ __('ui.landing.floating_note') }}</span>
                    <span class="floating-tag tag-two"><i class="bi bi-lock"></i> {{ __('ui.landing.floating_private') }}</span>

                    <div class="hero-mock">
                        <div class="mock-top">
                            <div class="mock-dots"><span></span><span></span><span></span></div>
                            <div class="mock-search"><i class="bi bi-search"></i> {{ __('ui.common.search_placeholder') }}</div>
                        </div>

                        <div class="mock-question">
                            <span class="avatar">RL</span>
                            {{ __('ui.landing.demo_search') }}
                        </div>

                        <div class="mock-answer">
                            <div class="mock-answer-head">
                                <span class="mock-spark"><i class="bi bi-stars"></i></span>
                                <span>{{ __('ui.search.recall') }}</span>
                                <span class="mock-confidence">{{ __('ui.search.confidence_high') }}</span>
                            </div>
                            <p>{!! __('ui.landing.demo_answer') !!}</p>
                            <div class="mock-source">
                                <span class="mock-source-icon"><i class="bi bi-receipt"></i></span>
                                <span class="mock-source-copy">
                                    <strong>{{ __('ui.landing.demo_source') }}</strong>
                                    <span>{{ __('ui.memory.evidence_note') }}</span>
                                </span>
                                <i class="bi bi-arrow-up-right"></i>
                            </div>

                            <div class="mock-grid">
                                <div class="mock-mini">
                                    <span class="mock-mini-icon"><i class="bi bi-tools"></i></span>
                                    <span class="mock-mini-copy">
                                        <strong>{{ __('ui.types.repair') }}</strong>
                                        <span>Walton fridge · 2025</span>
                                    </span>
                                </div>
                                <div class="mock-mini">
                                    <span class="mock-mini-icon"><i class="bi bi-airplane"></i></span>
                                    <span class="mock-mini-copy">
                                        <strong>{{ __('ui.types.travel') }}</strong>
                                        <span>Padma Bridge · 2026</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hero-marquee">
                <div class="marquee-inner">
                    <span><i class="bi bi-receipt"></i> {{ __('ui.types.receipt') }}</span>
                    <span><i class="bi bi-camera"></i> {{ __('ui.dashboard.capture_photo') }}</span>
                    <span><i class="bi bi-file-earmark-text"></i> {{ __('ui.types.document') }}</span>
                    <span><i class="bi bi-mic"></i> {{ __('ui.dashboard.capture_voice') }}</span>
                    <span><i class="bi bi-link-45deg"></i> {{ __('ui.dashboard.capture_link') }}</span>
                    <span><i class="bi bi-translate"></i> বাংলা + English</span>
                </div>
            </div>
        </div>
    </header>

    <section class="landing-section" id="how">
        <div class="container-wide">
            <div class="landing-section-head">
                <span class="eyebrow">{{ __('ui.landing.how_eyebrow') }}</span>
                <h2>{{ __('ui.landing.how_title') }}</h2>
                <p>{{ __('ui.landing.how_body') }}</p>
            </div>

            <div class="how-grid">
                <article class="how-card">
                    <div class="how-card-number">01</div>
                    <div class="how-icon"><i class="bi bi-plus-lg"></i></div>
                    <h3>{{ __('ui.landing.step_1_title') }}</h3>
                    <p>{{ __('ui.landing.step_1_body') }}</p>
                </article>
                <article class="how-card">
                    <div class="how-card-number">02</div>
                    <div class="how-icon"><i class="bi bi-stars"></i></div>
                    <h3>{{ __('ui.landing.step_2_title') }}</h3>
                    <p>{{ __('ui.landing.step_2_body') }}</p>
                </article>
                <article class="how-card">
                    <div class="how-card-number">03</div>
                    <div class="how-icon"><i class="bi bi-chat-square-quote"></i></div>
                    <h3>{{ __('ui.landing.step_3_title') }}</h3>
                    <p>{{ __('ui.landing.step_3_body') }}</p>
                </article>
            </div>
        </div>
    </section>

    <section class="landing-section light-alt" id="bilingual">
        <div class="container-wide">
            <div class="bilingual-grid">
                <div class="bilingual-copy">
                    <span class="eyebrow">{{ __('ui.landing.bilingual_eyebrow') }}</span>
                    <h2>{{ __('ui.landing.bilingual_title') }}</h2>
                    <p>{{ __('ui.landing.bilingual_body') }}</p>
                    <div class="privacy-points">
                        <div class="privacy-point">
                            <i class="bi bi-check2-circle"></i>
                            <span>{{ __('ui.landing.feature_capture') }}</span>
                        </div>
                        <div class="privacy-point">
                            <i class="bi bi-check2-circle"></i>
                            <span>{{ __('ui.landing.feature_search') }}</span>
                        </div>
                        <div class="privacy-point">
                            <i class="bi bi-check2-circle"></i>
                            <span>{{ __('ui.landing.feature_connected') }}</span>
                        </div>
                    </div>
                </div>

                <div class="bilingual-board">
                    <div class="board-lang-tabs">
                        <button type="button" class="board-lang-tab active">বাংলা</button>
                        <button type="button" class="board-lang-tab">English</button>
                    </div>
                    <div class="board-body">
                        <div class="board-question">
                            <i class="bi bi-search"></i>
                            {{ __('ui.landing.demo_search') }}
                        </div>
                        <div class="board-answer">
                            <span class="board-label"><i class="bi bi-stars"></i> {{ __('ui.search.evidence_based') }}</span>
                            <p>{{ __('ui.landing.demo_answer') }}</p>
                            <div class="board-source">
                                <i class="bi bi-receipt"></i>
                                <span><strong>{{ __('ui.landing.demo_source') }}</strong><br>{{ __('ui.memory.evidence_note') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="privacy-section" id="privacy">
        <div class="container-wide">
            <div class="privacy-grid">
                <div class="privacy-copy">
                    <span class="eyebrow">{{ __('ui.landing.privacy_eyebrow') }}</span>
                    <h2>{{ __('ui.landing.privacy_title') }}</h2>
                    <p>{{ __('ui.landing.privacy_body') }}</p>

                    <div class="privacy-points">
                        <div class="privacy-point">
                            <i class="bi bi-shield-lock"></i>
                            <span>{{ __('ui.landing.privacy_point_1') }}</span>
                        </div>
                        <div class="privacy-point">
                            <i class="bi bi-link-45deg"></i>
                            <span>{{ __('ui.landing.privacy_point_2') }}</span>
                        </div>
                        <div class="privacy-point">
                            <i class="bi bi-box-arrow-up-right"></i>
                            <span>{{ __('ui.landing.privacy_point_3') }}</span>
                        </div>
                    </div>
                </div>

                <div class="privacy-art">
                    <div class="privacy-lock-card">
                        <span class="privacy-lock"><i class="bi bi-lock"></i></span>
                        <h3>{{ __('ui.common.private') }}</h3>
                        <p>{{ __('ui.landing.floating_private') }}</p>
                        <div class="privacy-line-item">
                            <i class="bi bi-check2"></i>
                            {{ __('ui.landing.privacy_point_1') }}
                        </div>
                        <div class="privacy-line-item">
                            <i class="bi bi-check2"></i>
                            {{ __('ui.landing.privacy_point_3') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="landing-cta">
        <div class="container-narrow">
            <h2>{{ __('ui.landing.cta_title') }}</h2>
            <p>{{ __('ui.landing.cta_body') }}</p>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-accent btn-lg">{{ __('ui.nav.home') }} <i class="bi bi-arrow-right"></i></a>
            @else
                <a href="{{ route('register') }}" class="btn btn-accent btn-lg">{{ __('ui.landing.start_free') }} <i class="bi bi-arrow-right"></i></a>
            @endauth
        </div>
    </section>

    <footer class="landing-footer">
        <div class="container-wide landing-footer-inner">
            <a href="{{ route('home') }}" class="brand">
                <span class="brand-mark"><i class="bi bi-journal-bookmark-fill"></i></span>
                <span class="brand-copy">
                    <span class="brand-name">{{ __('ui.app.name') }}</span>
                    <span class="brand-tag">{{ __('ui.landing.footer_note') }}</span>
                </span>
            </a>
            <p>&copy; {{ now()->year }} {{ __('ui.app.name') }} · {{ __('ui.landing.footer_note') }}</p>
            <div class="footer-links">
                <a href="{{ route('privacy') }}">{{ __('ui.common.privacy') }}</a>
                <a href="{{ route('login') }}">{{ __('ui.auth.login') }}</a>
                <a href="mailto:support@realitylog.app">{{ __('ui.nav.help') }}</a>
            </div>
        </div>
    </footer>
</body>
</html>
