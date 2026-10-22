<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load([
            'settings',
            'collections',
            'entities',
            'reminders.memory:id,title',
            'memories' => fn ($query) => $query->with(['attachments', 'entities', 'tags', 'collections'])->orderBy('captured_at'),
        ]);

        $payload = [
            'export' => [
                'format' => 'realitylog-json-v1',
                'generated_at' => now()->toIso8601String(),
                'record_count' => $user->memories->count(),
                'note' => __('ui.export.note'),
            ],
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'settings' => $user->settings?->only([
                'interface_locale', 'ai_locale', 'timezone', 'country', 'currency', 'date_format', 'theme',
            ]),
            'collections' => $user->collections->map->only(['name', 'description', 'color']),
            'entities' => $user->entities->map->only(['type', 'name', 'metadata']),
            'memories' => $user->memories->map(fn ($memory) => [
                'id' => $memory->id,
                'title' => $memory->title,
                'memory_type' => $memory->memory_type,
                'source_type' => $memory->source_type,
                'source_url' => $memory->source_url,
                'body' => $memory->body,
                'extracted_text' => $memory->extracted_text,
                'summary' => $memory->summary,
                'detected_language' => $memory->detected_language,
                'event_date' => $memory->event_date?->toDateString(),
                'captured_at' => $memory->captured_at->toIso8601String(),
                'processing_status' => $memory->processing_status,
                'ai_metadata' => $memory->ai_metadata,
                'is_favorite' => $memory->is_favorite,
                'is_archived' => $memory->is_archived,
                'tags' => $memory->tags->pluck('name'),
                'collections' => $memory->collections->pluck('name'),
                'entities' => $memory->entities->map(fn ($entity) => [
                    'type' => $entity->type,
                    'name' => $entity->name,
                    'provenance' => $entity->pivot->provenance,
                    'confidence' => $entity->pivot->confidence,
                ]),
                'attachments' => $memory->attachments->map(fn ($attachment) => [
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'kind' => $attachment->kind,
                    'status' => $attachment->status,
                ]),
            ]),
            'reminders' => $user->reminders->map(fn ($reminder) => [
                'title' => $reminder->title,
                'type' => $reminder->type,
                'notes' => $reminder->notes,
                'due_at' => $reminder->due_at->toIso8601String(),
                'status' => $reminder->status,
                'linked_memory' => $reminder->memory?->title,
            ]),
        ];

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'data.exported',
            'ip_address' => $request->ip(),
        ]);

        $filename = 'realitylog-export-'.now()->format('Y-m-d-His').'.json';

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
