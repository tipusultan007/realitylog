@extends('layouts.auth')

@section('title', __('ui.auth.login'))

@section('content')
    <h2>{{ __('ui.auth.welcome_back') }}</h2>
    <p>{{ __('ui.auth.login_subtitle') }}</p>

    <form method="POST" action="{{ route('login.store') }}" class="auth-form">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="email">{{ __('ui.auth.email') }}</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror"
                placeholder="you@example.com"
                autocomplete="email"
                required
                autofocus
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">{{ __('ui.auth.password') }}</label>
            <input
                id="password"
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                placeholder="••••••••"
                autocomplete="current-password"
                required
            >
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">{{ __('ui.auth.remember') }}</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">{{ __('ui.auth.forgot') }}</a>
        </div>

        <button type="submit" class="btn btn-accent">
            {{ __('ui.auth.login') }}
            <i class="bi bi-arrow-right"></i>
        </button>
    </form>

    @if (config('app.env') === 'local' && config('realitylog.demo', false))
        <div class="auth-divider">{{ __('ui.auth.demo_title') }}</div>

        <div class="demo-card">
            <div class="demo-card-head">
                <strong>{{ __('ui.auth.demo_title') }}</strong>
                <i class="bi bi-stars text-accent"></i>
            </div>
            <p>{{ __('ui.auth.demo_body') }}</p>
            <div class="demo-credentials">
                <span><strong>{{ __('ui.auth.email') }}:</strong> {{ __('ui.auth.demo_email') }}</span>
                <span><strong>{{ __('ui.auth.password') }}:</strong> {{ __('ui.auth.demo_password') }}</span>
            </div>
            <button
                type="button"
                class="btn btn-soft btn-sm w-100"
                onclick="document.getElementById('email').value='{{ __('ui.auth.demo_email') }}';document.getElementById('password').value='{{ __('ui.auth.demo_password') }}';"
            >
                <i class="bi bi-box-arrow-in-right"></i>
                {{ __('ui.auth.use_demo') }}
            </button>
        </div>
    @endif

    <p class="auth-switch">
        {{ __('ui.auth.no_account') }}
        <a href="{{ route('register') }}">{{ __('ui.auth.create_one') }}</a>
    </p>
@endsection
