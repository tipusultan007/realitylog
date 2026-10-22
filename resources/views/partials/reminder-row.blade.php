@props(['reminder', 'showActions' => false])

<div class="reminder-row">
    <span class="reminder-datebox">
        <span class="day">{{ $reminder->due_at->translatedFormat('d') }}</span>
        <span class="month">{{ $reminder->due_at->translatedFormat('M') }}</span>
    </span>
    <span class="reminder-copy">
        <strong>{{ $reminder->title }}</strong>
        <span>
            {{ $reminder->typeLabel() }}
            @if ($reminder->memory)
                · {{ $reminder->memory->title }}
            @endif
        </span>
    </span>

    @if ($reminder->isOverdue())
        <span class="badge-soft red">{{ __('ui.reminders.overdue') }}</span>
    @elseif ($reminder->daysAway() === 0)
        <span class="badge-soft orange">{{ __('ui.reminders.due_today') }}</span>
    @elseif ($reminder->daysAway() === 1)
        <span class="badge-soft gold">{{ __('ui.reminders.due_tomorrow') }}</span>
    @else
        <span class="badge-soft neutral">{{ __('ui.reminders.due_in', ['days' => $reminder->daysAway()]) }}</span>
    @endif

    @if ($showActions)
        <div class="d-flex gap-1 ms-2">
            <form method="POST" action="{{ route('reminders.complete', $reminder) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="icon-btn-plain" title="{{ __('ui.common.complete') }}" aria-label="{{ __('ui.common.complete') }}">
                    <i class="bi bi-check2"></i>
                </button>
            </form>
        </div>
    @endif
</div>
