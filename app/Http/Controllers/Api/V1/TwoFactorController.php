<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Services\Security\ApiTokenService;
use App\Services\Security\TwoFactorAuthenticator;
use App\Services\Security\TwoFactorChallenge;
use App\Services\Security\TwoFactorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'enabled' => $user->two_factor_confirmed_at !== null,
                'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
                'recovery_codes_remaining' => $user->two_factor_confirmed_at === null
                    ? 0
                    : count($user->two_factor_recovery_codes ?? []),
            ],
        ]);
    }

    public function setup(Request $request, TwoFactorManager $manager): JsonResponse
    {
        if ($request->user()->two_factor_confirmed_at !== null) {
            return response()->json([
                'message' => __('ui.two_factor.already_enabled'),
            ], 409);
        }

        $data = $manager->begin($request->user());

        return response()->json([
            'data' => $data,
        ]);
    }

    public function confirm(Request $request, TwoFactorManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $codes = $manager->confirm($request->user(), $validated['code']);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'two_factor.enabled',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => __('ui.two_factor.enabled'),
            'recovery_codes' => $codes,
        ]);
    }

    public function disable(Request $request, TwoFactorManager $manager): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $manager->disable($user);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'two_factor.disabled',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => __('ui.two_factor.disabled'),
        ]);
    }

    public function challenge(
        Request $request,
        TwoFactorChallenge $challenge,
        TwoFactorAuthenticator $authenticator,
        ApiTokenService $tokens,
    ): JsonResponse {
        $validated = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        [$user, $deviceName, $nonce] = $challenge->consume($validated['challenge_token']);

        if (! $authenticator->verify($user, (string) $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => __('ui.two_factor.invalid_code'),
            ]);
        }

        $challenge->markUsed($nonce);
        $user->load('settings');
        [$plain, $token] = $tokens->issue($user, $deviceName);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'api.login',
            'meta' => ['two_factor' => true],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => __('ui.api.signed_in'),
            'token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'user' => UserResource::make($user)->resolve(),
        ]);
    }
}
