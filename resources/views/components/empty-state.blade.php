@props(['icon' => 'bi-journal-plus', 'title', 'body', 'action' => null, 'actionLabel' => null, 'actionRoute' => null])

<div class="empty-state">
    <span class="empty-icon"><i class="bi {{ $icon }}"></i></span>
    <h3>{{ $title }}</h3>
    <p>{{ $body }}</p>
    @if ($actionRoute && $actionLabel)
        <a href="{{ $actionRoute }}" class="btn btn-accent">
            <i class="bi bi-plus-lg"></i>
            {{ $actionLabel }}
        </a>
    @elseif ($action)
        {{ $action }}
    @endif
</div>
