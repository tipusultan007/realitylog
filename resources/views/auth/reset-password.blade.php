@extends('layouts.auth')

@section('title', __('ui.auth.reset_title'))

@section('content')
    <h2>{{ __('ui.auth.reset_title') }}</h2>
    <p>{{ __('ui.auth.reset_subtitle') }}</p>

    <form method="POST" action="{{ route('password.store') }}" class="auth-form">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label class="form-label" for="email">{{ __('ui.auth.email') }}</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $email) }}"
                class="form-control @error('email') is-invalid @enderror"
                autocomplete="email"
                required
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">{{ __('ui.auth.new_password') }}</label>
            <input
                id="password"
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                autocomplete="new-password"
                required
                autofocus
            >
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password_confirmation">{{ __('ui.auth.password_confirm') }}</label>
            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                class="form-control"
                autocomplete="new-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-accent">
            {{ __('ui.auth.reset_submit') }}
            <i class="bi bi-key"></i>
        </button>
    </form>
@endsection
