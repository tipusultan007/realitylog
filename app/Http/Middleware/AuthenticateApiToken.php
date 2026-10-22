<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return $this->unauthorized($request);
        }

        $token = ApiToken::query()
            ->with('user.settings')
            ->where('token_hash', hash('sha256', $bearer))
            ->first();

        if (! $token || ! $token->user || $token->isExpired()) {
            return $this->unauthorized($request);
        }

        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $this->applyLocale($request, $token->user->settings?->interface_locale);

        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }

    private function unauthorized(Request $request): JsonResponse
    {
        $this->applyLocale($request, null);

        return response()->json([
            'message' => __('ui.api.unauthenticated'),
        ], 401);
    }

    private function applyLocale(Request $request, ?string $fallback): void
    {
        $locale = $request->header('X-Locale') ?: $fallback ?: 'en';

        if (! in_array($locale, ['en', 'bn'], true)) {
            $locale = 'en';
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);
    }
}
