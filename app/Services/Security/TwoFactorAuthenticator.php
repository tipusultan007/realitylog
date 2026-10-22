<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorAuthenticator
{
    public function __construct(private readonly TotpService $totp) {}

    /**
     * Verify a TOTP code or a single-use recovery code.
     */
    public function verify(User $user, string $code): bool
    {
        if (! $user->two_factor_secret || $user->two_factor_confirmed_at === null) {
            return false;
        }

        $code = trim($code);
        $step = $this->totp->verify((string) $user->two_factor_secret, $code);

        if ($step !== null) {
            if ($user->two_factor_last_used_step !== null && $step <= $user->two_factor_last_used_step) {
                return false;
            }

            $user->forceFill(['two_factor_last_used_step' => $step])->save();

            return true;
        }

        return $this->useRecoveryCode($user, $code);
    }

    private function useRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $normalized = Str::upper(trim($code));

        foreach ($codes as $index => $hash) {
            if (Hash::check($normalized, $hash)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }
}
