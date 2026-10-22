@extends('layouts.auth')

@section('title', __('ui.auth.verify_title'))

@section('content')
    <h2>{{ __('ui.auth.verify_title') }}</h2>
    <p>{{ __('ui.auth.verify_body') }}</p>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-accent">
                {{ __('ui.auth.verify_resend') }}
                <i class="bi bi-envelope-check"></i>
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-soft">{{ __('ui.nav.logout') }}</button>
        </form>
    </div>
@endsection
