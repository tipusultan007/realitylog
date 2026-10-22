<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('ui.reminders.email_subject', ['title' => $reminder->title]) }}</title>
</head>
<body style="font-family: system-ui, sans-serif; color: #24312B; line-height: 1.6;">
    <h1 style="font-size: 22px;">{{ $reminder->title }}</h1>

    <p>{{ __('ui.reminders.email_intro') }}</p>

    <p>
        <strong>{{ __('ui.reminders.due') }}:</strong>
        {{ $reminder->due_at->translatedFormat('j F Y, H:i') }}
    </p>

    @if ($reminder->notes)
        <p>{{ $reminder->notes }}</p>
    @endif

    @if ($reminder->memory)
        <p>
            <strong>{{ __('ui.reminders.linked_memory') }}:</strong>
            {{ $reminder->memory->title }}
        </p>
    @endif

    <p>
        <a href="{{ $url }}">{{ __('ui.reminders.open_in_realitylog') }}</a>
    </p>

    <p style="color: #68756C; font-size: 13px;">
        {{ __('ui.reminders.email_footer') }}
    </p>
</body>
</html>
