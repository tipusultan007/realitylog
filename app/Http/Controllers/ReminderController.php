<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReminderRequest;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $month = now()->startOfMonth();
        $remindersThisMonth = $user->reminders()
            ->whereBetween('due_at', [$month, $month->copy()->endOfMonth()])
            ->where('status', '!=', 'dismissed')
            ->get()
            ->groupBy(fn (Reminder $reminder) => $reminder->due_at->format('j'));

        return view('reminders.index', [
            'upcoming' => $user->reminders()
                ->where('status', 'upcoming')
                ->where('due_at', '>=', now())
                ->with('memory')
                ->orderBy('due_at')
                ->get(),
            'overdue' => $user->reminders()
                ->where('status', 'upcoming')
                ->where('due_at', '<', now())
                ->with('memory')
                ->orderBy('due_at')
                ->get(),
            'completed' => $user->reminders()
                ->where('status', 'completed')
                ->with('memory')
                ->latest('completed_at')
                ->limit(12)
                ->get(),
            'memories' => $user->memories()->active()->orderBy('title')->get(['id', 'title']),
            'calendarMonth' => $month,
            'calendarDays' => collect(range(1, $month->daysInMonth))->map(fn (int $day) => $month->copy()->day($day)),
            'remindersThisMonth' => $remindersThisMonth,
        ]);
    }

    public function store(StoreReminderRequest $request): RedirectResponse
    {
        $request->user()->reminders()->create([
            'memory_id' => $request->validated('memory_id'),
            'title' => $request->validated('title'),
            'notes' => $request->validated('notes'),
            'type' => $request->validated('type'),
            'due_at' => $request->validated('due_at'),
            'notification_channel' => $request->validated('notification_channel') ?? 'in_app',
            'status' => 'upcoming',
        ]);

        return back()->with('status', __('ui.reminders.created'));
    }

    public function complete(Request $request, Reminder $reminder): RedirectResponse
    {
        $this->authorizeReminder($request, $reminder);

        $reminder->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('status', __('ui.reminders.completed_message'));
    }

    public function snooze(Request $request, Reminder $reminder): RedirectResponse
    {
        $this->authorizeReminder($request, $reminder);

        $newDueAt = $reminder->due_at->copy()->addWeek();

        if ($newDueAt->isPast()) {
            $newDueAt = now()->addWeek();
        }

        $reminder->update([
            'due_at' => $newDueAt,
            'status' => 'upcoming',
        ]);

        return back()->with('status', __('ui.reminders.created'));
    }

    public function destroy(Request $request, Reminder $reminder): RedirectResponse
    {
        $this->authorizeReminder($request, $reminder);

        $reminder->delete();

        return back()->with('status', __('ui.reminders.deleted_message'));
    }

    private function authorizeReminder(Request $request, Reminder $reminder): void
    {
        abort_unless($reminder->user_id === $request->user()->id, 403);
    }
}
