@props(['memory', 'listView' => false])

@php
    $icon = match ($memory->memory_type) {
        'receipt' => 'bi-receipt',
        'purchase' => 'bi-bag-check',
        'repair' => 'bi-tools',
        'travel' => 'bi-airplane',
        'document' => 'bi-file-earmark-text',
        'meeting' => 'bi-people',
        'place' => 'bi-geo-alt',
        'idea' => 'bi-lightbulb',
        'warranty' => 'bi-shield-check',
        'medical' => 'bi-heart-pulse',
        'person' => 'bi-person',
        'link' => 'bi-link-45deg',
        'event' => 'bi-calendar-heart',
        default => 'bi-journal-text',
    };
    $cover = $memory->coverUrl();
    $collection = $memory->collections->first();
@endphp

<article class="memory-card {{ $listView ? 'list-view' : '' }}" data-memory-type="{{ $memory->memory_type }}">
    <a href="{{ route('memories.show', $memory) }}" class="memory-card-cover" aria-label="{{ $memory->title }}">
        @if ($cover)
            <img src="{{ $cover }}" alt="{{ $memory->title }}" loading="lazy">
        @else
            <span class="cover-abstract">
                <span class="cover-glyph"><i class="bi {{ $icon }}"></i></span>
                <span class="cover-word">{{ \Illuminate\Support\Str::limit($memory->title, 26) }}</span>
            </span>
        @endif
    </a>

    <form method="POST" action="{{ route('memories.favorite', $memory) }}" class="position-absolute" style="right: 0.7rem; top: 0.7rem; z-index: 4;">
        @csrf
        @method('PATCH')
        <button type="submit" class="memory-favorite {{ $memory->is_favorite ? '' : 'is-muted' }}" style="position: static;" aria-label="{{ $memory->is_favorite ? __('ui.common.unfavorite') : __('ui.common.favorite') }}">
            <i class="bi {{ $memory->is_favorite ? 'bi-heart-fill' : 'bi-heart' }}"></i>
        </button>
    </form>

    <div class="memory-card-body">
        <div class="memory-meta-row">
            <span class="memory-type">
                <i class="bi {{ $icon }}"></i>
                {{ $memory->typeLabel() }}
            </span>
            <span class="memory-date">{{ $memory->displayDate() }}</span>
        </div>

        <h3>
            <a href="{{ route('memories.show', $memory) }}">{{ $memory->title }}</a>
        </h3>

        <p class="memory-excerpt">{{ $memory->excerpt(140) }}</p>

        <div class="memory-footer">
            <i class="bi bi-chat-square-text"></i>
            <span>{{ $memory->languageName() }}</span>
            @if ($memory->attachments->isNotEmpty())
                <span class="dot"></span>
                <i class="bi bi-paperclip"></i>
                <span>{{ $memory->attachments->count() }}</span>
            @endif
            @if ($collection)
                <span class="dot"></span>
                <span class="memory-tag">{{ $collection->name }}</span>
            @endif
            @if ($memory->processing_status !== 'ready')
                <span class="memory-status">
                    <i class="bi bi-hourglass-split"></i>
                    {{ $memory->statusLabel() }}
                </span>
            @endif
        </div>
    </div>
</article>
