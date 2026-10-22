<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Security\ApiTokenService;
use App\Services\Security\TwoFactorChallenge;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, ApiTokenService $tokens): JsonResponse
    {
        $this->applyLocale($request);

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'name' => trim((string) $request->input('name')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'locale' => ['nullable', 'in:en,bn'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ]);

        $locale = $validated['locale'] ?? $this->requestedLocale($request);

        $user = DB::transaction(function () use ($validated, $locale): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            UserSetting::create([
                'user_id' => $user->id,
                'interface_locale' => $locale,
                'ai_locale' => $locale,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'account.created',
                'ip_address' => request()->ip(),
            ]);

            return $user;
        });

        $user->load('settings');
        event(new Registered($user));
        [$plain, $token] = $tokens->issue($user, $validated['device_name'] ?? null);

        return response()->json([
            'message' => __('ui.api.registered'),
            'token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'user' => UserResource::make($user)->resolve(),
        ], 201);
    }

    public function login(
        Request $request,
        ApiTokenService $tokens,
        TwoFactorChallenge $challenge,
    ): JsonResponse {
        $this->applyLocale($request);

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('ui.auth.failed'),
            ]);
        }

        if ($user->two_factor_confirmed_at !== null) {
            $pending = $challenge->create($user, $validated['device_name'] ?? null);

            return response()->json([
                'message' => __('ui.two_factor.challenge_required'),
                'two_factor_required' => true,
                'challenge_token' => $pending['token'],
                'challenge_expires_at' => $pending['expires_at']->toIso8601String(),
            ]);
        }

        $user->load('settings');
        [$plain, $token] = $tokens->issue($user, $validated['device_name'] ?? null);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'api.login',
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

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('settings');

        return response()->json([
            'data' => UserResource::make($user)->resolve(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var ApiToken|null $token */
        $token = $request->attributes->get('api_token');
        $token?->delete();

        return response()->json([
            'message' => __('ui.api.signed_out'),
        ]);
    }

    private function applyLocale(Request $request): void
    {
        $locale = $this->requestedLocale($request);

        App::setLocale($locale);
        Carbon::setLocale($locale);
    }

    private function requestedLocale(Request $request): string
    {
        $locale = (string) ($request->input('locale') ?? $request->header('X-Locale') ?? 'en');

        return in_array($locale, ['en', 'bn'], true) ? $locale : 'en';
    }
}
