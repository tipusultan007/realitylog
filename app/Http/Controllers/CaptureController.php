<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemoryRequest;
use App\Services\Capture\CaptureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaptureController extends Controller
{
    public function create(Request $request): View
    {
        return view('capture.create', [
            'collections' => $request->user()->collections()->orderBy('name')->get(),
            'defaultType' => in_array($request->query('type'), ['text', 'image', 'document', 'voice', 'link'], true)
                ? $request->query('type')
                : 'text',
        ]);
    }

    public function store(StoreMemoryRequest $request, CaptureService $capture): RedirectResponse
    {
        $memory = $capture->capture(
            $request->user(),
            $request->safe()->except('file'),
            $request->file('file')
        );

        return redirect()
            ->route('memories.show', $memory)
            ->with('status', $memory->processing_status === 'needs_review'
                ? __('ui.capture.captured_moment').' '.__('ui.memory.needs_review')
                : __('ui.capture.captured_moment'));
    }
}
