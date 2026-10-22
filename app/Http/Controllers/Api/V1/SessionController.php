<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ApiToken|null $current */
        $current = $request->attributes->get('api_token');

        $sessions = $request->user()
            ->apiTokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApiToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'is_current' => $current?->is($token) ?? false,
                'created_at' => $token->created_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ])
            ->all();

        return response()->json(['data' => $sessions]);
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        /** @var ApiToken|null $current */
        $current = $request->attributes->get('api_token');

        $session = $request->user()->apiTokens()->whereKey($token)->first();

        abort_unless($session, 404);

        $isCurrent = $current?->is($session) ?? false;

        $session->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'session.revoked',
            'subject_type' => ApiToken::class,
            'subject_id' => $token,
            'meta' => ['current' => $isCurrent],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => $isCurrent ? __('ui.api.signed_out') : __('ui.sessions.revoked'),
            'revoked_current' => $isCurrent,
        ]);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        /** @var ApiToken|null $current */
        $current = $request->attributes->get('api_token');

        $query = $request->user()->apiTokens();

        if ($current) {
            $query->whereKeyNot($current->getKey());
        }

        $revoked = $query->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'session.revoked_others',
            'meta' => ['count' => $revoked],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => __('ui.sessions.revoked_others'),
            'revoked' => $revoked,
        ]);
    }
}
