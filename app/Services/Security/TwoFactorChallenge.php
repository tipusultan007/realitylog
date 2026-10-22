<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A short-lived, single-use token that bridges a correct password to a second
 * factor challenge. It is encrypted so it cannot be forged or inspected.
 */
class TwoFactorChallenge
{
    private const TTL_MINUTES = 10;

    /**
     * @return array{token: string, expires_at: Carbon}
     */
    public function create(User $user, ?string $deviceName): array
    {
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);
        $payload = [
            'user_id' => $user->id,
            'nonce' => Str::random(40),
            'device_name' => $deviceName,
            'exp' => $expiresAt->getTimestamp(),
        ];

        return [
            'token' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array{0: User, 1: ?string, 2: string}
     */
    public function consume(string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->invalid();
        }

        if (! is_array($payload)
            || ! isset($payload['user_id'], $payload['nonce'], $payload['exp'])
            || (int) $payload['exp'] < now()->getTimestamp()
            || Cache::has($this->cacheKey((string) $payload['nonce']))) {
            $this->invalid();
        }

        $user = User::query()->find($payload['user_id']);

        if (! $user || $user->two_factor_confirmed_at === null) {
            $this->invalid();
        }

        return [
            $user,
            is_string($payload['device_name'] ?? null) ? $payload['device_name'] : null,
            (string) $payload['nonce'],
        ];
    }

    public function markUsed(string $nonce): void
    {
        Cache::put($this->cacheKey($nonce), true, now()->addMinutes(self::TTL_MINUTES + 5));
    }

    private function cacheKey(string $nonce): string
    {
        return 'two-factor-challenge:'.$nonce;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages([
            'challenge_token' => __('ui.two_factor.challenge_invalid'),
        ]);
    }
}
