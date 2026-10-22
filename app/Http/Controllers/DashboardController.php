<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $today = now();

        $recentMemories = $user->memories()
            ->active()
            ->with(['attachments', 'entities', 'tags'])
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

        $totalMemories = $user->memories()->active()->count();
        $memoryIds = collect($request->session()->get('recent_memories', []))->take(4);

        $recentlyViewed = $memoryIds->isEmpty()
            ? collect()
            : $user->memories()
                ->whereIn('id', $memoryIds)
                ->with(['attachments', 'entities', 'tags'])
                ->get()
                ->sortBy(fn ($memory) => $memoryIds->search($memory->id))
                ->values();

        $recentSearches = $user->searchHistories()
            ->latest('created_at')
            ->limit(5)
            ->get();

        $onThisDay = $user->memories()
            ->active()
            ->whereNotNull('event_date')
            ->whereMonth('event_date', $today->month)
            ->whereDay('event_date', $today->day)
            ->latest('event_date')
            ->limit(3)
            ->get();

        $storageBytes = (int) $user->memories()
            ->withSum('attachments as attachment_size', 'size')
            ->get()
            ->sum('attachment_size');

        $greeting = match (true) {
            $today->hour < 12 => __('ui.dashboard.greeting_morning'),
            $today->hour < 17 => __('ui.dashboard.greeting_afternoon'),
            $today->hour < 22 => __('ui.dashboard.greeting_evening'),
            default => __('ui.dashboard.greeting_night'),
        };

        return view('dashboard', [
            'greeting' => $greeting,
            'recentMemories' => $recentMemories,
            'upcomingReminders' => $upcomingReminders,
            'typeBreakdown' => $typeBreakdown,
            'totalMemories' => $totalMemories,
            'recentlyViewed' => $recentlyViewed,
            'recentSearches' => $recentSearches,
            'onThisDay' => $onThisDay,
            'storageBytes' => $storageBytes,
            'totalDocuments' => $user->memories()->active()->where('source_type', 'document')->count(),
            'favoriteCount' => $user->memories()->active()->favorites()->count(),
            'reminderCount' => $user->reminders()->where('status', 'upcoming')->count(),
            'processingCount' => $user->memories()->active()->whereIn('processing_status', ['queued', 'processing', 'needs_review'])->count(),
        ]);
    }
}
