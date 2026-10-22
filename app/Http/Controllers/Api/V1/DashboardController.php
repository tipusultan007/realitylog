<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemoryResource;
use App\Http\Resources\ReminderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now();

        $recentMemories = $user->memories()
            ->active()
            ->with(['attachments', 'entities', 'tags', 'collections'])
            ->latest('captured_at')
            ->limit(6)
            ->get();

        $upcomingReminders = $user->reminders()
            ->where('status', 'upcoming')
            ->with('memory')
            ->orderBy('due_at')
            ->limit(4)
            ->get();

        $typeBreakdown = $user->memories()
            ->active()
            ->selectRaw('memory_type, COUNT(*) as total')
            ->groupBy('memory_type')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $storageBytes = (int) $user->memories()
            ->withSum('attachments as attachment_size', 'size')
            ->get()
            ->sum('attachment_size');

        $onThisDay = $user->memories()
            ->active()
            ->whereNotNull('event_date')
            ->whereMonth('event_date', $today->month)
            ->whereDay('event_date', $today->day)
            ->with('attachments')
            ->latest('event_date')
            ->limit(3)
            ->get();

        $greeting = match (true) {
            $today->hour < 12 => __('ui.dashboard.greeting_morning'),
            $today->hour < 17 => __('ui.dashboard.greeting_afternoon'),
            $today->hour < 22 => __('ui.dashboard.greeting_evening'),
            default => __('ui.dashboard.greeting_night'),
        };

        return response()->json([
            'greeting' => $greeting,
            'server_date' => $today->toIso8601String(),
            'stats' => [
                'memories' => $user->memories()->active()->count(),
                'documents' => $user->memories()->active()->where('source_type', 'document')->count(),
                'favorites' => $user->memories()->active()->favorites()->count(),
                'reminders' => $user->reminders()->where('status', 'upcoming')->count(),
                'processing' => $user->memories()->active()->whereIn('processing_status', ['queued', 'processing', 'needs_review'])->count(),
                'storage_bytes' => $storageBytes,
            ],
            'type_breakdown' => $typeBreakdown->map(fn ($row) => [
                'type' => $row->memory_type,
                'label' => __('ui.types.'.$row->memory_type),
                'total' => (int) $row->total,
            ])->all(),
            'recent_memories' => MemoryResource::collection($recentMemories)->resolve(),
            'upcoming_reminders' => ReminderResource::collection($upcomingReminders)->resolve(),
            'recent_searches' => $user->searchHistories()
                ->latest('created_at')
                ->limit(5)
                ->pluck('query')
                ->all(),
            'on_this_day' => MemoryResource::collection($onThisDay)->resolve(),
        ]);
    }
}
