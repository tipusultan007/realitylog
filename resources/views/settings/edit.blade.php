@extends('layouts.app')

@section('title', __('ui.settings.title'))

@php
    $dateFormats = [
        'd M Y' => now()->translatedFormat('d M Y'),
        'j F Y' => now()->translatedFormat('j F Y'),
        'd/m/Y' => now()->format('d/m/Y'),
        'Y-m-d' => now()->format('Y-m-d'),
    ];
    $quietStart = old('quiet_hours_start', $settings->quiet_hours_start ? substr($settings->quiet_hours_start, 0, 5) : '22:00');
    $quietEnd = old('quiet_hours_end', $settings->quiet_hours_end ? substr($settings->quiet_hours_end, 0, 5) : '07:00');
@endphp

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">{{ __('ui.nav.settings') }}</span>
            <h1>{{ __('ui.settings.title') }}</h1>
            <p>{{ __('ui.settings.subtitle') }}</p>
        </div>
        <div class="page-actions">
            <span class="privacy-note">
                <i class="bi bi-shield-lock"></i>
                {{ __('ui.settings.data_private') }}
            </span>
        </div>
    </div>

    <div class="settings-layout">
        <nav class="settings-nav" aria-label="{{ __('ui.settings.title') }}">
            <a href="#profile" class="active"><i class="bi bi-person"></i> {{ __('ui.settings.profile') }}</a>
            <a href="#language"><i class="bi bi-translate"></i> {{ __('ui.settings.language') }}</a>
            <a href="#notifications"><i class="bi bi-bell"></i> {{ __('ui.settings.notifications') }}</a>
            <a href="#privacy"><i class="bi bi-shield-check"></i> {{ __('ui.settings.privacy') }}</a>
            <a href="#security"><i class="bi bi-lock"></i> {{ __('ui.settings.security') }}</a>
        </nav>

        <div class="settings-main">
            <section class="panel settings-card" id="profile">
                <div class="settings-card-head">
                    <h2>{{ __('ui.settings.profile_title') }}</h2>
                    <p>{{ __('ui.settings.profile_sub') }}</p>
                </div>

                <form method="POST" action="{{ route('settings.profile') }}" class="settings-form">
                    @csrf
                    @method('PATCH')

                    <div class="form-grid">
                        <div class="span-2">
                            <label class="form-label" for="name">{{ __('ui.settings.display_name') }}</label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name', $currentUser->name) }}"
                                class="form-control @error('name') is-invalid @enderror"
                                required
                                maxlength="120"
                                autocomplete="name"
                            >
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="span-2">
                            <label class="form-label" for="email">{{ __('ui.settings.email') }}</label>
                            <input
                                id="email"
                                type="email"
                                value="{{ $currentUser->email }}"
                                class="form-control"
                                disabled
                            >
                        </div>

                        <div>
                            <label class="form-label" for="timezone">{{ __('ui.settings.timezone') }}</label>
                            <select id="timezone" name="timezone" class="form-select @error('timezone') is-invalid @enderror" required>
                                @foreach ($timezones as $timezone)
                                    <option value="{{ $timezone }}" @selected(old('timezone', $settings->timezone) === $timezone)>{{ $timezone }}</option>
                                @endforeach
                            </select>
                            @error('timezone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label" for="country">{{ __('ui.settings.country') }}</label>
                            <input
                                id="country"
                                type="text"
                                name="country"
                                value="{{ old('country', $settings->country) }}"
                                class="form-control text-uppercase @error('country') is-invalid @enderror"
                                required
                                minlength="2"
                                maxlength="2"
                                placeholder="BD"
                            >
                            @error('country')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label" for="currency">{{ __('ui.settings.currency') }}</label>
                            <input
                                id="currency"
                                type="text"
                                name="currency"
                                value="{{ old('currency', $settings->currency) }}"
                                class="form-control text-uppercase @error('currency') is-invalid @enderror"
                                required
                                minlength="3"
                                maxlength="3"
                                placeholder="BDT"
                            >
                            @error('currency')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="settings-submit">
                        <button type="submit" class="btn btn-accent">
                            <i class="bi bi-check2"></i>
                            {{ __('ui.settings.save_profile') }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="panel settings-card" id="language">
                <div class="settings-card-head">
                    <h2>{{ __('ui.settings.language_title') }}</h2>
                    <p>{{ __('ui.settings.language_sub') }}</p>
                </div>

                <form method="POST" action="{{ route('settings.language') }}" class="settings-form">
                    @csrf
                    @method('PATCH')

                    <div class="settings-row">
                        <div>
                            <h3>{{ __('ui.settings.interface_language') }}</h3>
                            <p>{{ __('ui.settings.interface_hint') }}</p>
                        </div>
                        <div class="language-selector">
                            @foreach (['en' => __('ui.languages.en'), 'bn' => __('ui.languages.bn')] as $locale => $label)
                                <label class="lang-choice {{ old('interface_locale', $settings->interface_locale) === $locale ? 'is-selected' : '' }}">
                                    <input type="radio" name="interface_locale" value="{{ $locale }}" class="visually-hidden" @checked(old('interface_locale', $settings->interface_locale) === $locale)>
                                    <i class="bi bi-translate"></i>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @error('interface_locale')
                        <div class="alert alert-danger mb-3">{{ $message }}</div>
                    @enderror

                    <div class="settings-row">
                        <div>
                            <h3>{{ __('ui.settings.ai_language') }}</h3>
                            <p>{{ __('ui.settings.ai_hint') }}</p>
                        </div>
                        <div class="language-selector">
                            @foreach (['en' => __('ui.languages.en'), 'bn' => __('ui.languages.bn')] as $locale => $label)
                                <label class="lang-choice {{ old('ai_locale', $settings->ai_locale) === $locale ? 'is-selected' : '' }}">
                                    <input type="radio" name="ai_locale" value="{{ $locale }}" class="visually-hidden" @checked(old('ai_locale', $settings->ai_locale) === $locale)>
                                    <i class="bi bi-stars"></i>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @error('ai_locale')
                        <div class="alert alert-danger mb-3">{{ $message }}</div>
                    @enderror

                    <div class="form-grid mt-3">
                        <div>
                            <label class="form-label" for="date_format">{{ __('ui.settings.date_format') }}</label>
                            <select id="date_format" name="date_format" class="form-select @error('date_format') is-invalid @enderror" required>
                                @foreach ($dateFormats as $value => $example)
                                    <option value="{{ $value }}" @selected(old('date_format', $settings->date_format) === $value)>{{ $example }} ({{ $value }})</option>
                                @endforeach
                            </select>
                            @error('date_format')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="settings-submit">
                        <button type="submit" class="btn btn-accent">
                            <i class="bi bi-check2"></i>
                            {{ __('ui.settings.save_language') }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="panel settings-card" id="notifications">
                <div class="settings-card-head">
                    <h2>{{ __('ui.settings.notifications_title') }}</h2>
                    <p>{{ __('ui.settings.notifications_sub') }}</p>
                </div>

                <form method="POST" action="{{ route('settings.notifications') }}" class="settings-form">
                    @csrf
                    @method('PATCH')

                    <div class="settings-row">
                        <div>
                            <h3>{{ __('ui.settings.in_app') }}</h3>
                            <p>{{ __('ui.reminders.no_auto_schedule') }}</p>
                        </div>
                        <div class="form-check form-switch">
                            <input
                                type="checkbox"
                                role="switch"
                                class="form-check-input"
                                id="notifications_enabled"
                                name="notifications_enabled"
                                value="1"
                                @checked(old('notifications_enabled', $settings->notifications_enabled))
                            >
                            <label class="form-check-label" for="notifications_enabled">{{ __('ui.settings.in_app') }}</label>
                        </div>
                    </div>

                    <div class="settings-row">
                        <div>
                            <h3>{{ __('ui.settings.email_reminders') }}</h3>
                            <p>{{ __('ui.settings.notifications_sub') }}</p>
                        </div>
                        <div class="form-check form-switch">
                            <input
                                type="checkbox"
                                role="switch"
                                class="form-check-input"
                                id="email_reminders"
                                name="email_reminders"
                                value="1"
                                @checked(old('email_reminders', $settings->email_reminders))
                            >
                            <label class="form-check-label" for="email_reminders">{{ __('ui.settings.email_reminders') }}</label>
                        </div>
                    </div>

                    <div class="settings-row">
                        <div>
                            <h3>{{ __('ui.settings.quiet_hours') }}</h3>
                            <p>{{ __('ui.reminders.time_hint', ['timezone' => $settings->timezone]) }}</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="time" name="quiet_hours_start" value="{{ $quietStart }}" class="form-control @error('quiet_hours_start') is-invalid @enderror" aria-label="{{ __('ui.settings.quiet_start') }}">
                            <span class="text-muted-soft small">{{ __('ui.common.of') }}</span>
                            <input type="time" name="quiet_hours_end" value="{{ $quietEnd }}" class="form-control @error('quiet_hours_end') is-invalid @enderror" aria-label="{{ __('ui.settings.quiet_end') }}">
                        </div>
                    </div>

                    <div class="settings-submit">
                        <button type="submit" class="btn btn-accent">
                            <i class="bi bi-check2"></i>
                            {{ __('ui.settings.save_notifications') }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="panel settings-card" id="privacy">
                <div class="settings-card-head">
                    <h2>{{ __('ui.settings.privacy_title') }}</h2>
                    <p>{{ __('ui.settings.privacy_sub') }}</p>
                </div>

                <div class="settings-form">
                    <form method="POST" action="{{ route('settings.privacy') }}">
                        @csrf
                        @method('PATCH')

                        <div class="settings-row">
                            <div>
                                <h3>{{ __('ui.settings.external_ai') }}</h3>
                                <p>{{ __('ui.settings.external_ai_hint') }}</p>
                            </div>
                            <div class="form-check form-switch">
                                <input
                                    type="checkbox"
                                    role="switch"
                                    class="form-check-input"
                                    id="external_ai_consent"
                                    name="external_ai_consent"
                                    value="1"
                                    @checked(old('external_ai_consent', $settings->external_ai_consent))
                                >
                                <label class="form-check-label" for="external_ai_consent">{{ __('ui.settings.external_ai') }}</label>
                            </div>
                        </div>

                        <div class="notice-box mt-3">
                            <i class="bi bi-info-circle"></i>
                            <span>{{ __('ui.settings.consent_note') }}</span>
                        </div>

                        <div class="settings-submit">
                            <button type="submit" class="btn btn-accent">
                                <i class="bi bi-check2"></i>
                                {{ __('ui.common.save') }}
                            </button>
                        </div>
                    </form>

                    <hr style="border-color: var(--line);">

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <h3 class="h6 mb-1">{{ __('ui.settings.export_title') }}</h3>
                            <p class="text-muted-soft small mb-0">{{ __('ui.settings.export_body') }}</p>
                        </div>
                        <a href="{{ route('export') }}" class="btn btn-soft">
                            <i class="bi bi-download"></i>
                            {{ __('ui.settings.export_button') }}
                        </a>
                    </div>
                </div>
            </section>

            <section class="panel settings-card danger-zone" id="security">
                <div class="settings-card-head">
                    <h2>{{ __('ui.settings.security_title') }}</h2>
                    <p>{{ __('ui.settings.security_sub') }}</p>
                </div>

                <div class="settings-form">
                    <div class="settings-row">
                        <div>
                            <h3>{{ __('ui.settings.current_session') }}</h3>
                            <p>{{ __('ui.settings.sessions_body') }}</p>
                        </div>
                        <span class="badge-soft green"><i class="bi bi-laptop"></i> {{ __('ui.settings.current_session') }}</span>
                    </div>

                    <div class="settings-row">
                        <div>
                            <h3>{{ __('ui.settings.password') }}</h3>
                            <p>{{ __('ui.settings.password_hint') }}</p>
                        </div>
                        <a href="#password-form" class="btn btn-soft btn-sm">
                            <i class="bi bi-key"></i>
                            {{ __('ui.settings.change_password') }}
                        </a>
                    </div>

                    <form method="POST" action="{{ route('settings.password') }}" class="mt-3" id="password-form">
                        @csrf
                        @method('PATCH')

                        <div class="form-grid">
                            <div>
                                <label class="form-label" for="current_password">{{ __('ui.settings.current_password') }}</label>
                                <input
                                    id="current_password"
                                    type="password"
                                    name="current_password"
                                    class="form-control @error('current_password') is-invalid @enderror"
                                    required
                                    autocomplete="current-password"
                                >
                                @error('current_password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="form-label" for="new_password">{{ __('ui.settings.new_password') }}</label>
                                <input
                                    id="new_password"
                                    type="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    required
                                    autocomplete="new-password"
                                >
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="form-label" for="new_password_confirmation">{{ __('ui.settings.confirm_new_password') }}</label>
                                <input
                                    id="new_password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    class="form-control"
                                    required
                                    autocomplete="new-password"
                                >
                            </div>
                        </div>

                        <p class="form-hint">{{ __('ui.settings.change_password_hint') }}</p>

                        <div class="d-flex justify-content-end mt-2">
                            <button type="submit" class="btn btn-soft btn-sm">
                                <i class="bi bi-shield-check"></i>
                                {{ __('ui.settings.save_password') }}
                            </button>
                        </div>
                    </form>

                    <div class="settings-row">
                        <div>
                            <h3>{{ __('ui.settings.mfa') }}</h3>
                            <p>{{ __('ui.settings.mfa_hint') }}</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge-soft {{ $currentUser->two_factor_confirmed_at ? 'green' : 'neutral' }}">
                                {{ $currentUser->two_factor_confirmed_at ? __('ui.settings.mfa_enabled') : __('ui.settings.mfa_disabled') }}
                            </span>
                            <a href="{{ route('settings.two-factor.show') }}" class="btn btn-soft btn-sm">
                                <i class="bi bi-shield-lock"></i>
                                {{ $currentUser->two_factor_confirmed_at ? __('ui.settings.mfa_manage') : __('ui.settings.mfa_setup') }}
                            </a>
                        </div>
                    </div>

                    <hr style="border-color: color-mix(in srgb, var(--danger) 25%, var(--line));">

                    <div class="mb-3">
                        <h3 class="h5" style="color: var(--danger);">{{ __('ui.settings.delete_title') }}</h3>
                        <p class="text-muted-soft small mb-0">{{ __('ui.settings.delete_body') }}</p>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('settings.account.destroy') }}"
                        class="panel panel-pad"
                        style="background: var(--danger-soft); border-color: transparent;"
                    >
                        @csrf
                        @method('DELETE')

                        <div class="form-grid">
                            <div>
                                <label class="form-label" for="delete-password">{{ __('ui.settings.password') }}</label>
                                <input
                                    id="delete-password"
                                    type="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    required
                                    autocomplete="current-password"
                                >
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="form-label" for="delete-confirmation">{{ __('ui.settings.delete_confirm') }}</label>
                                <input
                                    id="delete-confirmation"
                                    type="text"
                                    name="confirmation"
                                    class="form-control @error('confirmation') is-invalid @enderror"
                                    placeholder="DELETE"
                                    required
                                    autocomplete="off"
                                >
                                @error('confirmation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash3"></i>
                                {{ __('ui.settings.delete_button') }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
