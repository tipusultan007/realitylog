<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemoryResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $month = $this->resolveMonth((string) $request->query('month'));
        $type = (string) $request->query('type', 'all');

        $query = $request->user()->memories()
            ->active()
            ->with(['attachments', 'entities', 'tags', 'collections'])
            ->where(function ($builder) use ($month): void {
                $builder->whereBetween('event_date', [
                    $month->startOfMonth()->toDateString(),
                    $month->endOfMonth()->toDateString(),
                ])->orWhere(function ($inner) use ($month): void {
                    $inner->whereNull('event_date')
                        ->whereBetween('captured_at', [
                            $month->startOfMonth()->startOfDay(),
                            $month->endOfMonth()->endOfDay(),
                        ]);
                });
            })
            ->when($type !== 'all', fn ($builder) => $builder->where('memory_type', $type));

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

        return response()->json([
            'month' => $month->format('Y-m'),
            'previous_month' => $month->subMonthNoOverflow()->format('Y-m'),
            'next_month' => $month->addMonthNoOverflow()->format('Y-m'),
            'month_total' => $entries->flatten()->count(),
            'groups' => $entries->map(fn ($memories, $date) => [
                'date' => $date,
                'label' => CarbonImmutable::parse($date)->locale(app()->getLocale())->translatedFormat('l, j F Y'),
                'memories' => MemoryResource::collection($memories)->resolve(),
            ])->values()->all(),
            'undated' => MemoryResource::collection($undated)->resolve(),
            'type' => $type,
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
