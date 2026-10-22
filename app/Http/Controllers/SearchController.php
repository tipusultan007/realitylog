<?php

namespace App\Http\Controllers;

use App\Services\Search\RecallService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, RecallService $recall): View
    {
        $query = trim((string) $request->query('q'));
        $type = (string) $request->query('type', 'all');
        $date = (string) $request->query('date', 'all');
        $language = (string) $request->query('language', 'all');
        $filters = [
            'type' => in_array($type, ['purchase', 'receipt', 'document', 'person', 'place', 'event', 'repair', 'item', 'task', 'idea', 'travel', 'note', 'meeting', 'warranty', 'medical', 'link'], true) ? $type : 'all',
            'date' => in_array($date, ['last_week', 'last_month', 'last_year', 'this_year'], true) ? $date : 'all',
            'language' => in_array($language, ['en', 'bn'], true) ? $language : 'all',
            'favorite' => $request->boolean('favorite'),
            'include_archived' => $request->boolean('archived'),
        ];

        $result = $recall->search($request->user(), mb_substr($query, 0, 500), $filters);

        if ($query !== '') {
            $request->user()->searchHistories()->create([
                'query' => mb_substr($query, 0, 500),
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

        return view('search.index', [
            'query' => $query,
            'result' => $result,
            'filters' => $filters,
            'recentSearches' => $request->user()->searchHistories()->latest('created_at')->limit(5)->get(),
        ]);
    }
}
