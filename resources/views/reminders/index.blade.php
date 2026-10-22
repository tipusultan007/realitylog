@extends('layouts.app')

@section('title', __('ui.reminders.title'))

@php
    $typeIcons = [
        'custom' => 'bi-bell',
        'warranty' => 'bi-shield-check',
        'renewal' => 'bi-arrow-repeat',
        'follow_up' => 'bi-arrow-return-right',
        'maintenance' => 'bi-tools',
        'event' => 'bi-calendar-heart',
    ];
    $selectedMemoryId = old('memory_id', request('memory'));
    $defaultDueAt = old('due_at', now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'));
    $firstOffset = $calendarMonth->copy()->startOfMonth()->dayOfWeekIso - 1;
    $calendarCells = array_merge(array_fill(0, $firstOffset, null), range(1, $calendarMonth->daysInMonth));
    while (count($calendarCells) % 7 !== 0) {
        $calendarCells[] = null;
    }
    $weekdayLabels = collect(range(0, 6))->map(
        fn (int $offset) => $calendarMonth->copy()->startOfWeek()->addDays($offset)->translatedFormat('D')
    );
@endphp

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">{{ __('ui.nav.reminders') }}</span>
            <h1>{{ __('ui.reminders.title') }}</h1>
            <p>{{ __('ui.reminders.subtitle') }}</p>
        </div>
        <div class="page-actions">
            <a href="#add-reminder" class="btn btn-accent">
                <i class="bi bi-plus-lg"></i>
                {{ __('ui.reminders.add') }}
            </a>
        </div>
    </div>

    <div class="reminder-layout">
        <div>
            @if ($overdue->isNotEmpty())
                <section class="reminder-group" aria-labelledby="reminders-overdue">
                    <h2 class="reminder-group-title" id="reminders-overdue">
                        {{ __('ui.reminders.overdue') }}
                        <span class="badge-soft red">{{ $overdue->count() }}</span>
                    </h2>

                    @foreach ($overdue as $reminder)
                        <article class="full-reminder is-overdue">
                            <form method="POST" action="{{ route('reminders.complete', $reminder) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="reminder-check" title="{{ __('ui.common.complete') }}" aria-label="{{ __('ui.common.complete') }}">
                                    <i class="bi bi-check2"></i>
                                </button>
                            </form>

                            <span class="reminder-type-icon"><i class="bi {{ $typeIcons[$reminder->type] ?? 'bi-bell' }}"></i></span>

                            <div class="full-reminder-copy">
                                <strong>{{ $reminder->title }}</strong>
                                <span>
                                    {{ $reminder->due_at->translatedFormat('l, j M Y, g:i A') }}
                                    · {{ $reminder->typeLabel() }}
                                    @if ($reminder->memory)
                                        · <a href="{{ route('memories.show', $reminder->memory) }}">{{ $reminder->memory->title }}</a>
                                    @endif
                                </span>
                                @if ($reminder->notes)
                                    <span class="d-block mt-1">{{ $reminder->notes }}</span>
                                @endif
                            </div>

                            <div class="full-reminder-actions">
                                <span class="badge-soft red">{{ __('ui.reminders.overdue_by', ['days' => abs($reminder->daysAway())]) }}</span>

                                <form method="POST" action="{{ route('reminders.snooze', $reminder) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-soft btn-sm" title="{{ __('ui.reminders.snooze_week') }}">
                                        <i class="bi bi-clock-history"></i>
                                        {{ __('ui.common.snooze') }}
                                    </button>
                                </form>

                                <form
                                    method="POST"
                                    action="{{ route('reminders.destroy', $reminder) }}"
                                    data-confirm
                                    data-confirm-title="{{ __('ui.reminders.dismiss') }}?"
                                    data-confirm-body="{{ __('ui.common.cannot_undo') }}"
                                    data-confirm-label="{{ __('ui.common.delete') }}"
                                    data-confirm-danger="true"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon" title="{{ __('ui.reminders.dismiss') }}" aria-label="{{ __('ui.reminders.dismiss') }}" style="color: var(--danger);">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </section>
            @endif

            <section class="reminder-group" aria-labelledby="reminders-upcoming">
                <h2 class="reminder-group-title" id="reminders-upcoming">
                    {{ __('ui.reminders.upcoming') }}
                    <span class="badge-soft green">{{ $upcoming->count() }}</span>
                </h2>

                @forelse ($upcoming as $reminder)
                    <article class="full-reminder">
                        <form method="POST" action="{{ route('reminders.complete', $reminder) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="reminder-check" title="{{ __('ui.common.complete') }}" aria-label="{{ __('ui.common.complete') }}">
                                <i class="bi bi-check2"></i>
                            </button>
                        </form>

                        <span class="reminder-type-icon"><i class="bi {{ $typeIcons[$reminder->type] ?? 'bi-bell' }}"></i></span>

                        <div class="full-reminder-copy">
                            <strong>{{ $reminder->title }}</strong>
                            <span>
                                {{ $reminder->due_at->translatedFormat('l, j M Y, g:i A') }}
                                · {{ $reminder->typeLabel() }}
                                @if ($reminder->memory)
                                    · <a href="{{ route('memories.show', $reminder->memory) }}">{{ $reminder->memory->title }}</a>
                                @endif
                            </span>
                            @if ($reminder->notes)
                                <span class="d-block mt-1">{{ $reminder->notes }}</span>
                            @endif
                        </div>

                        <div class="full-reminder-actions">
                            @if ($reminder->daysAway() === 0)
                                <span class="badge-soft orange">{{ __('ui.reminders.due_today') }}</span>
                            @elseif ($reminder->daysAway() === 1)
                                <span class="badge-soft gold">{{ __('ui.reminders.due_tomorrow') }}</span>
                            @else
                                <span class="badge-soft neutral">{{ __('ui.reminders.due_in', ['days' => $reminder->daysAway()]) }}</span>
                            @endif

                            <form method="POST" action="{{ route('reminders.snooze', $reminder) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-icon" title="{{ __('ui.reminders.snooze_week') }}" aria-label="{{ __('ui.reminders.snooze_week') }}">
                                    <i class="bi bi-clock-history"></i>
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('reminders.destroy', $reminder) }}"
                                data-confirm
                                data-confirm-title="{{ __('ui.reminders.dismiss') }}?"
                                data-confirm-body="{{ __('ui.common.cannot_undo') }}"
                                data-confirm-label="{{ __('ui.common.delete') }}"
                                data-confirm-danger="true"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon" title="{{ __('ui.reminders.dismiss') }}" aria-label="{{ __('ui.reminders.dismiss') }}" style="color: var(--danger);">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="panel panel-pad">
                        <div class="d-flex align-items-center gap-3">
                            <span class="empty-icon mb-0" style="width: 52px; height: 52px; font-size: 1.05rem;"><i class="bi bi-bell-slash"></i></span>
                            <div>
                                <strong>{{ __('ui.reminders.empty_upcoming') }}</strong>
                                <p class="mb-0 text-muted-soft small">{{ __('ui.reminders.empty_upcoming_body') }}</p>
                            </div>
                        </div>
                    </div>
                @endforelse
            </section>

            <section class="reminder-group" aria-labelledby="reminders-completed">
                <h2 class="reminder-group-title" id="reminders-completed">
                    {{ __('ui.reminders.completed') }}
                    <span class="badge-soft neutral">{{ $completed->count() }}</span>
                </h2>

                @forelse ($completed as $reminder)
                    <article class="full-reminder">
                        <span class="reminder-check" style="background: var(--success); border-color: var(--success); color: #fff;">
                            <i class="bi bi-check2"></i>
                        </span>

                        <span class="reminder-type-icon"><i class="bi {{ $typeIcons[$reminder->type] ?? 'bi-bell' }}"></i></span>

                        <div class="full-reminder-copy">
                            <strong>{{ $reminder->title }}</strong>
                            <span>
                                {{ __('ui.common.complete') }}
                                · {{ $reminder->completed_at?->translatedFormat('j M Y') ?? $reminder->due_at->translatedFormat('j M Y') }}
                                @if ($reminder->memory)
                                    · <a href="{{ route('memories.show', $reminder->memory) }}">{{ $reminder->memory->title }}</a>
                                @endif
                            </span>
                        </div>

                        <div class="full-reminder-actions">
                            <span class="badge-soft green">{{ __('ui.reminders.completed') }}</span>

                            <form
                                method="POST"
                                action="{{ route('reminders.destroy', $reminder) }}"
                                data-confirm
                                data-confirm-title="{{ __('ui.reminders.dismiss') }}?"
                                data-confirm-body="{{ __('ui.common.cannot_undo') }}"
                                data-confirm-label="{{ __('ui.common.delete') }}"
                                data-confirm-danger="true"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon" title="{{ __('ui.reminders.dismiss') }}" aria-label="{{ __('ui.reminders.dismiss') }}" style="color: var(--danger);">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="text-muted-soft small">{{ __('ui.reminders.empty_completed') }}</p>
                @endforelse
            </section>
        </div>

        <aside class="detail-side">
            <section class="panel calendar-panel">
                <div class="calendar-head">
                    <strong>{{ $calendarMonth->translatedFormat('F Y') }}</strong>
                    <span>{{ trans_choice('ui.reminders.count', $remindersThisMonth->flatten()->count(), [], app()->getLocale()) }}</span>
                </div>

                <div class="calendar-grid">
                    @foreach ($weekdayLabels as $label)
                        <span class="calendar-weekday">{{ $label }}</span>
                    @endforeach

                    @foreach ($calendarCells as $day)
                        @if ($day === null)
                            <span class="calendar-day is-empty" aria-hidden="true"></span>
                        @else
                            @php($dayReminders = $remindersThisMonth->get((string) $day))
                            <div
                                class="calendar-day {{ $dayReminders?->isNotEmpty() ? 'has-reminder' : '' }} {{ $calendarMonth->isSameMonth(now()) && now()->day === $day ? 'is-today' : '' }}"
                                @if ($dayReminders?->isNotEmpty())
                                    title="{{ $dayReminders->pluck('title')->implode(', ') }}"
                                @endif
                            >
                                {{ $day }}
                            </div>
                        @endif
                    @endforeach
                </div>

                <p class="reminder-note">{{ __('ui.reminders.time_hint', ['timezone' => $currentSettings?->timezone ?? config('app.timezone')]) }}</p>
            </section>

            <section class="panel" id="add-reminder">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">{{ __('ui.reminders.add_title') }}</h2>
                        <p class="panel-subtitle">{{ __('ui.reminders.add_subtitle') }}</p>
                    </div>
                </div>

                <div class="panel-body">
                    <form method="POST" action="{{ route('reminders.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="reminder-title">{{ __('ui.reminders.title_field') }}</label>
                            <input
                                id="reminder-title"
                                type="text"
                                name="title"
                                value="{{ old('title') }}"
                                class="form-control @error('title') is-invalid @enderror"
                                placeholder="{{ __('ui.reminders.title_placeholder') }}"
                                maxlength="180"
                                required
                            >
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="due_at">{{ __('ui.reminders.date_field') }}</label>
                            <input
                                id="due_at"
                                type="datetime-local"
                                name="due_at"
                                value="{{ $defaultDueAt }}"
                                class="form-control @error('due_at') is-invalid @enderror"
                                required
                            >
                            @error('due_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="reminder-type">{{ __('ui.reminders.type_field') }}</label>
                            <select id="reminder-type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                                @foreach (['custom', 'warranty', 'renewal', 'follow_up', 'maintenance', 'event'] as $type)
                                    <option value="{{ $type }}" @selected(old('type', 'custom') === $type)>{{ __('ui.reminder_types.'.$type) }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="memory_id">{{ __('ui.reminders.linked_memory') }}</label>
                            <select id="memory_id" name="memory_id" class="form-select @error('memory_id') is-invalid @enderror">
                                <option value="">{{ __('ui.reminders.no_memory') }}</option>
                                @foreach ($memories as $memory)
                                    <option value="{{ $memory->id }}" @selected((string) $selectedMemoryId === (string) $memory->id)>{{ $memory->title }}</option>
                                @endforeach
                            </select>
                            @error('memory_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="notes">{{ __('ui.reminders.notes_field') }}</label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="3"
                                class="form-control @error('notes') is-invalid @enderror"
                                placeholder="{{ __('ui.reminders.notes_placeholder') }}"
                                maxlength="2000"
                            >{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-accent w-100">
                            <i class="bi bi-bell"></i>
                            {{ __('ui.reminders.save') }}
                        </button>

                        <p class="text-muted-soft small mt-3 mb-0">{{ __('ui.reminders.no_auto_schedule') }}</p>
                    </form>
                </div>
            </section>
        </aside>
    </div>
@endsection
