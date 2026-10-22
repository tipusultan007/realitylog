<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReminderRequest;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'overdue' => ReminderResource::collection(
                $user->reminders()
                    ->where('status', 'upcoming')
                    ->where('due_at', '<', now())
                    ->with('memory')
                    ->orderBy('due_at')
                    ->get()
            )->resolve(),
            'upcoming' => ReminderResource::collection(
                $user->reminders()
                    ->where('status', 'upcoming')
                    ->where('due_at', '>=', now())
                    ->with('memory')
                    ->orderBy('due_at')
                    ->get()
            )->resolve(),
            'completed' => ReminderResource::collection(
                $user->reminders()
                    ->where('status', 'completed')
                    ->with('memory')
                    ->latest('completed_at')
                    ->limit(20)
                    ->get()
            )->resolve(),
            'memories' => $user->memories()
                ->active()
                ->orderBy('title')
                ->get(['id', 'title', 'memory_type'])
                ->map(fn ($memory) => [
                    'id' => $memory->id,
                    'title' => $memory->title,
                    'memory_type' => $memory->memory_type,
                    'type_label' => __('ui.types.'.$memory->memory_type),
                ])->all(),
        ]);
    }

    public function store(StoreReminderRequest $request): JsonResponse
    {
        $reminder = $request->user()->reminders()->create([
            'memory_id' => $request->validated('memory_id'),
            'title' => $request->validated('title'),
            'notes' => $request->validated('notes'),
            'type' => $request->validated('type'),
            'due_at' => $request->validated('due_at'),
            'notification_channel' => $request->validated('notification_channel') ?? 'in_app',
            'status' => 'upcoming',
        ]);

        $reminder->load('memory');

        return response()->json([
            'message' => __('ui.reminders.created'),
            'data' => ReminderResource::make($reminder)->resolve(),
        ], 201);
    }

    public function complete(Request $request, Reminder $reminder): JsonResponse
    {
        $this->authorizeReminder($request, $reminder);

        $reminder->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $reminder->load('memory');

        return response()->json([
            'message' => __('ui.reminders.completed_message'),
            'data' => ReminderResource::make($reminder)->resolve(),
        ]);
    }

    public function snooze(Request $request, Reminder $reminder): JsonResponse
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

        $reminder->load('memory');

        return response()->json([
            'message' => __('ui.reminders.created'),
            'data' => ReminderResource::make($reminder)->resolve(),
        ]);
    }

    public function destroy(Request $request, Reminder $reminder): JsonResponse
    {
        $this->authorizeReminder($request, $reminder);

        $reminder->delete();

        return response()->json([
            'message' => __('ui.reminders.deleted_message'),
        ]);
    }

    private function authorizeReminder(Request $request, Reminder $reminder): void
    {
        abort_unless($reminder->user_id === $request->user()->id, 403);
    }
}
