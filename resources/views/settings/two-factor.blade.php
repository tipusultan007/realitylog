@extends('layouts.app')

@section('title', __('ui.settings.mfa'))

@section('content')
    <nav class="detail-breadcrumb" aria-label="{{ __('ui.nav.settings') }}">
        <a href="{{ route('settings.edit') }}">{{ __('ui.nav.settings') }}</a>
        <i class="bi bi-chevron-right"></i>
        <span>{{ __('ui.settings.mfa') }}</span>
    </nav>

    <div class="page-head">
        <div>
            <span class="eyebrow">{{ __('ui.settings.security') }}</span>
            <h1>{{ __('ui.settings.mfa') }}</h1>
            <p>{{ __('ui.settings.mfa_hint') }}</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if (! empty($recoveryCodes))
        <section class="panel panel-pad mb-3">
            <h2 class="panel-title">{{ __('ui.settings.mfa_recovery_codes') }}</h2>
            <p class="panel-subtitle">{{ __('ui.settings.mfa_recovery_hint') }}</p>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($recoveryCodes as $code)
                    <code class="badge-soft neutral">{{ $code }}</code>
                @endforeach
            </div>
        </section>
    @endif

    @if ($enabled)
        <section class="panel panel-pad">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h2 class="panel-title">{{ __('ui.settings.mfa_enabled') }}</h2>
                    <p class="panel-subtitle mb-0">{{ __('ui.settings.mfa_hint') }}</p>
                </div>
                <span class="badge-soft green"><i class="bi bi-shield-check"></i> {{ __('ui.settings.mfa_enabled') }}</span>
            </div>

            <hr>

            <form method="POST" action="{{ route('settings.two-factor.destroy') }}">
                @csrf
                @method('DELETE')

                <label class="form-label" for="mfa-password">{{ __('ui.settings.current_password') }}</label>
                <input
                    id="mfa-password"
                    type="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    required
                    autocomplete="current-password"
                >
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror

                <button type="submit" class="btn btn-danger mt-3">
                    <i class="bi bi-shield-slash"></i>
                    {{ __('ui.settings.mfa_disable') }}
                </button>
            </form>
        </section>
    @else
        <section class="panel panel-pad">
            <h2 class="panel-title">{{ __('ui.settings.mfa_setup') }}</h2>
            <p class="panel-subtitle">{{ __('ui.settings.mfa_setup_hint') }}</p>

            @if ($setup)
                <div class="mb-3">
                    <span class="form-label">{{ __('ui.settings.mfa_secret') }}</span>
                    <div><code class="fs-5">{{ $setup['secret'] }}</code></div>
                </div>

                <p class="small text-muted-soft">
                    <a href="{{ $setup['otpauth_uri'] }}">{{ __('ui.settings.mfa_setup') }}</a>
                </p>

                <form method="POST" action="{{ route('settings.two-factor.confirm') }}">
                    @csrf

                    <label class="form-label" for="mfa-code">{{ __('ui.auth.two_factor_code') }}</label>
                    <input
                        id="mfa-code"
                        type="text"
                        name="code"
                        class="form-control @error('code') is-invalid @enderror"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        required
                    >
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror

                    <button type="submit" class="btn btn-accent mt-3">
                        <i class="bi bi-shield-check"></i>
                        {{ __('ui.settings.mfa_confirm') }}
                    </button>
                </form>
            @endif
        </section>
    @endif

    <p class="mt-3">
        <a href="{{ route('settings.edit') }}">{{ __('ui.common.back') }}</a>
    </p>
@endsection
