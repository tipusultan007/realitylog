@extends('layouts.app')

@section('title', __('ui.help.title'))

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">{{ __('ui.nav.help') }}</span>
            <h1>{{ __('ui.help.title') }}</h1>
            <p>{{ __('ui.help.subtitle') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('capture.create') }}" class="btn btn-accent">
                <i class="bi bi-plus-lg"></i>
                {{ __('ui.nav.capture') }}
            </a>
        </div>
    </div>

    <div class="help-layout">
        <div>
            <section class="panel panel-pad">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="panel-title">{{ __('ui.help.faq') }}</h2>
                        <p class="panel-subtitle">{{ __('ui.help.subtitle') }}</p>
                    </div>
                    <span class="badge-soft neutral"><i class="bi bi-question-circle"></i> FAQ</span>
                </div>

                <details class="faq-item" open>
                    <summary>{{ __('ui.help.q1') }}</summary>
                    <p>{{ __('ui.help.a1') }}</p>
                </details>

                <details class="faq-item">
                    <summary>{{ __('ui.help.q2') }}</summary>
                    <p>{{ __('ui.help.a2') }}</p>
                </details>

                <details class="faq-item">
                    <summary>{{ __('ui.help.q3') }}</summary>
                    <p>{{ __('ui.help.a3') }}</p>
                </details>

                <details class="faq-item">
                    <summary>{{ __('ui.help.q4') }}</summary>
                    <p>{{ __('ui.help.a4') }}</p>
                </details>

                <details class="faq-item">
                    <summary>{{ __('ui.help.q5') }}</summary>
                    <p>{{ __('ui.help.a5') }}</p>
                </details>
            </section>

            <section class="panel panel-pad mt-3">
                <div class="d-flex align-items-start gap-3">
                    <span class="empty-icon mb-0" style="width: 52px; height: 52px; font-size: 1.05rem;"><i class="bi bi-lightbulb"></i></span>
                    <div>
                        <h2 class="h6 mb-1">{{ __('ui.capture.processing_title') }}</h2>
                        <p class="text-muted-soft small mb-0">{{ __('ui.capture.processing_note') }}</p>
                    </div>
                </div>
            </section>
        </div>

        <aside class="detail-side">
            <section class="panel panel-pad support-card">
                <span class="eyebrow">{{ __('ui.help.contact') }}</span>
                <h2 class="panel-title mt-2">{{ __('ui.help.contact') }}</h2>
                <p class="text-muted-soft small">{{ __('ui.help.contact_body') }}</p>

                <a href="mailto:support@realitylog.app" class="btn btn-accent w-100">
                    <i class="bi bi-envelope"></i>
                    support@realitylog.app
                </a>
            </section>

            <section class="panel panel-pad">
                <div class="side-title">
                    <span>{{ __('ui.common.learn_more') }}</span>
                    <i class="bi bi-book"></i>
                </div>

                <div class="d-grid gap-2">
                    <a href="{{ route('privacy') }}" class="suggestion-pill">
                        <i class="bi bi-shield-lock"></i>
                        {{ __('ui.privacy.title') }}
                    </a>
                    <a href="{{ route('settings.edit') }}#language" class="suggestion-pill">
                        <i class="bi bi-translate"></i>
                        {{ __('ui.settings.language_title') }}
                    </a>
                    <a href="{{ route('settings.edit') }}#privacy" class="suggestion-pill">
                        <i class="bi bi-download"></i>
                        {{ __('ui.settings.export_title') }}
                    </a>
                </div>
            </section>

            <section class="panel panel-pad">
                <div class="privacy-note">
                    <i class="bi bi-shield-check"></i>
                    {{ __('ui.privacy.evidence_body') }}
                </div>
            </section>
        </aside>
    </div>
@endsection
