@extends('layouts.auth')

@section('title', __('ui.auth.two_factor_title'))

@section('content')
    <h2>{{ __('ui.auth.two_factor_title') }}</h2>
    <p>{{ __('ui.auth.two_factor_body') }}</p>

    <form method="POST" action="{{ route('two-factor.verify') }}" class="auth-form">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="code">{{ __('ui.auth.two_factor_code') }}</label>
            <input
                id="code"
                type="text"
                name="code"
                value="{{ old('code') }}"
                class="form-control @error('code') is-invalid @enderror"
                placeholder="123456"
                inputmode="numeric"
                autocomplete="one-time-code"
                autofocus
                required
            >
            @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-accent">
            {{ __('ui.auth.two_factor_submit') }}
            <i class="bi bi-shield-check"></i>
        </button>
    </form>

    <form method="POST" action="{{ route('two-factor.cancel') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-link p-0">{{ __('ui.auth.two_factor_cancel') }}</button>
    </form>

    <p class="auth-switch">{{ __('ui.auth.two_factor_hint') }}</p>
@endsection
