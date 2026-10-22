<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

class MetaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $locale = (string) ($request->header('X-Locale') ?? $request->query('locale') ?? 'en');
        $locale = in_array($locale, ['en', 'bn'], true) ? $locale : 'en';

        App::setLocale($locale);
        Carbon::setLocale($locale);

        $types = [
            'purchase', 'receipt', 'document', 'person', 'place', 'event',
            'repair', 'item', 'task', 'idea', 'travel', 'note', 'meeting',
            'warranty', 'medical', 'link',
        ];

        $sources = ['text', 'image', 'document', 'voice', 'link'];
        $reminderTypes = ['custom', 'warranty', 'renewal', 'follow_up', 'maintenance', 'event'];

        return response()->json([
            'locale' => $locale,
            'types' => collect($types)->map(fn (string $type) => [
                'value' => $type,
                'label' => __('ui.types.'.$type),
            ])->all(),
            'sources' => collect($sources)->map(fn (string $source) => [
                'value' => $source,
                'label' => __('ui.sources.'.$source),
            ])->all(),
            'reminder_types' => collect($reminderTypes)->map(fn (string $type) => [
                'value' => $type,
                'label' => __('ui.reminder_types.'.$type),
            ])->all(),
            'languages' => [
                ['value' => 'en', 'label' => __('ui.languages.en')],
                ['value' => 'bn', 'label' => __('ui.languages.bn')],
            ],
            'date_formats' => ['d M Y', 'j F Y', 'd/m/Y', 'Y-m-d'],
        ]);
    }
}
