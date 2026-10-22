@extends('layouts.auth')

@section('title', __('ui.auth.create_account'))

@section('content')
    <h2>{{ __('ui.auth.create_account') }}</h2>
    <p>{{ __('ui.auth.register_subtitle') }}</p>

    <form method="POST" action="{{ route('register.store') }}" class="auth-form">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="name">{{ __('ui.auth.name') }}</label>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                class="form-control @error('name') is-invalid @enderror"
                placeholder="Rezaul Karim"
                autocomplete="name"
                required
                autofocus
            >
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

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
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row g-2 mb-3">
            <div class="col-sm-6">
                <label class="form-label" for="password">{{ __('ui.auth.password') }}</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    placeholder="••••••••"
                    autocomplete="new-password"
                    required
                >
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="password_confirmation">{{ __('ui.auth.password_confirm') }}</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    class="form-control"
                    placeholder="••••••••"
                    autocomplete="new-password"
                    required
                >
            </div>
        </div>

        <div class="mb-3">
            <span class="form-label d-block">{{ __('ui.auth.language_choice') }}</span>
            <div class="segmented" role="radiogroup" aria-label="{{ __('ui.auth.language_choice') }}">
                <label>
                    <input type="radio" name="locale" value="en" {{ old('locale', $interfaceLocale) === 'en' ? 'checked' : '' }}>
                    <span>English</span>
                </label>
                <label>
                    <input type="radio" name="locale" value="bn" {{ old('locale', $interfaceLocale) === 'bn' ? 'checked' : '' }}>
                    <span>বাংলা</span>
                </label>
            </div>
            <div class="form-hint">{{ __('ui.auth.language_hint') }}</div>
            @error('locale')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-accent">
            {{ __('ui.auth.register') }}
            <i class="bi bi-arrow-right"></i>
        </button>
    </form>

    <p class="auth-switch">
        {{ __('ui.auth.have_account') }}
        <a href="{{ route('login') }}">{{ __('ui.auth.sign_in_instead') }}</a>
    </p>

    <p class="auth-legal">
        {{ __('ui.auth.terms') }}
        <br>
        <a href="{{ route('privacy') }}">{{ __('ui.privacy.title') }}</a>
    </p>
@endsection
