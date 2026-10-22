<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemoryResource;
use App\Services\Search\RecallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, RecallService $recall): JsonResponse
    {
        $query = trim(mb_substr((string) $request->query('q'), 0, 500));
        $type = (string) $request->query('type', 'all');
        $date = (string) $request->query('date', 'all');
        $language = (string) $request->query('language', 'all');

        $filters = [
            'type' => in_array($type, [
                'purchase', 'receipt', 'document', 'person', 'place', 'event', 'repair',
                'item', 'task', 'idea', 'travel', 'note', 'meeting', 'warranty', 'medical', 'link',
            ], true) ? $type : 'all',
            'date' => in_array($date, ['last_week', 'last_month', 'last_year', 'this_year'], true) ? $date : 'all',
            'language' => in_array($language, ['en', 'bn'], true) ? $language : 'all',
            'favorite' => $request->boolean('favorite'),
            'include_archived' => $request->boolean('archived'),
        ];

        $result = $recall->search($request->user(), $query, $filters);

        if ($query !== '') {
            $request->user()->searchHistories()->create([
                'query' => $query,
                'locale' => app()->getLocale(),
                'results_count' => $result['total'],
            ]);

            $request->user()->searchHistories()
                ->latest('created_at')
                ->skip(20)
                ->take(50)
                ->get()
                ->each
                ->delete();
        }

        $dateRange = $result['date_range'];

        return response()->json([
            'query' => $query,
            'locale' => $result['locale'],
            'has_answer' => $result['has_answer'],
            'answer' => $result['answer'],
            'answer_kind' => $result['answer_kind'],
            'confidence' => $result['confidence'],
            'confidence_label' => $result['confidence_label'],
            'intent' => $result['intent'],
            'total' => $result['total'],
            'too_short' => $result['too_short'],
            'semantic_engine' => $result['semantic_engine'],
            'date_range' => $dateRange ? [
                'label' => $dateRange['label'],
                'start' => $dateRange['start']->toDateString(),
                'end' => $dateRange['end']->toDateString(),
            ] : null,
            'sources' => array_map(fn (array $source) => [
                'match_type' => $source['match_type'],
                'excerpt' => $source['excerpt'],
                'score' => $source['score'],
                'similarity' => $source['similarity'],
                'vector_match' => $source['vector_match'],
                'date' => $source['date'],
                'memory' => MemoryResource::make($source['memory'])->resolve(),
            ], $result['sources']),
            'results' => array_map(fn (array $item) => [
                'match_type' => $item['match_type'],
                'score' => $item['score'],
                'similarity' => $item['similarity'],
                'vector_match' => $item['vector_match'],
                'exact_matches' => $item['exact_matches'],
                'semantic_matches' => $item['semantic_matches'],
                'memory' => MemoryResource::make($item['memory'])->resolve(),
            ], $result['results']),
            'filters' => $filters,
            'recent_searches' => $request->user()->searchHistories()
                ->latest('created_at')
                ->limit(5)
                ->pluck('query')
                ->all(),
        ]);
    }
}
