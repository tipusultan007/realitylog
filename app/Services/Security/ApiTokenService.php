<?php

namespace App\Services\Security;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

class ApiTokenService
{
    /**
     * @return array{0: string, 1: ApiToken}
     */
    public function issue(User $user, ?string $deviceName): array
    {
        $plain = 'rl_'.Str::random(60);
        $expiresAt = now()->addDays(90);

        $user->apiTokens()->whereNotNull('expires_at')->where('expires_at', '<', now())->delete();

        $expired = $user->apiTokens()->orderByDesc('created_at')->skip(9)->take(20)->pluck('id');
        if ($expired->isNotEmpty()) {
            $user->apiTokens()->whereIn('id', $expired)->delete();
        }

        $token = $user->apiTokens()->create([
            'name' => Str::limit(trim((string) $deviceName) ?: 'android', 80, ''),
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
        ]);

        return [$plain, $token];
    }
}
