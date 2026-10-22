<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimelineController extends Controller
{
    public function index(Request $request): View
    {
        $month = $this->resolveMonth((string) $request->query('month'));

        $query = $request->user()->memories()
            ->active()
            ->with(['attachments', 'entities', 'tags'])
            ->where(function ($builder) use ($month) {
                $builder->whereBetween('event_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
                    ->orWhere(function ($inner) use ($month) {
                        $inner->whereNull('event_date')
                            ->whereBetween('captured_at', [$month->startOfMonth()->startOfDay(), $month->endOfMonth()->endOfDay()]);
                    });
            })
            ->when($request->filled('type') && $request->query('type') !== 'all', fn ($builder) => $builder->where('memory_type', $request->query('type')));

        $entries = $query
            ->get()
            ->sortByDesc(fn ($memory) => ($memory->event_date ?? $memory->captured_at)->timestamp)
            ->groupBy(fn ($memory) => ($memory->event_date ?? $memory->captured_at)->format('Y-m-d'));

        $undated = $request->user()->memories()
            ->active()
            ->whereNull('event_date')
            ->with(['attachments', 'tags'])
            ->latest('captured_at')
            ->limit(5)
            ->get();

        return view('timeline.index', [
            'month' => $month,
            'previousMonth' => $month->subMonthNoOverflow(),
            'nextMonth' => $month->addMonthNoOverflow(),
            'entries' => $entries,
            'undated' => $undated,
            'type' => (string) $request->query('type', 'all'),
            'monthTotal' => $entries->flatten()->count(),
        ]);
    }

    private function resolveMonth(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m', $value);

            if ($parsed) {
                return $parsed->startOfMonth();
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
