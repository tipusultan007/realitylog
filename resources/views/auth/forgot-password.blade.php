@extends('layouts.auth')

@section('title', __('ui.auth.forgot_title'))

@section('content')
    <h2>{{ __('ui.auth.forgot_title') }}</h2>
    <p>{{ __('ui.auth.forgot_subtitle') }}</p>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="email">{{ __('ui.auth.email') }}</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror"
                autocomplete="email"
                required
                autofocus
            >
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-accent">
            {{ __('ui.auth.send_reset_link') }}
            <i class="bi bi-envelope-arrow-up"></i>
        </button>
    </form>

    <p class="auth-switch">
        <a href="{{ route('login') }}">{{ __('ui.auth.back_to_login') }}</a>
    </p>
@endsection
