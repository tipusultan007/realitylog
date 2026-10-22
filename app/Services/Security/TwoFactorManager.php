<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TwoFactorManager
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly TwoFactorAuthenticator $authenticator,
    ) {}

    /**
     * @return array{secret: string, otpauth_uri: string, issuer: string, account: string}
     */
    public function begin(User $user): array
    {
        if ($user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages([
                'code' => __('ui.two_factor.already_enabled'),
            ]);
        }

        $secret = $user->two_factor_secret ?: $this->totp->generateSecret();

        $user->forceFill(['two_factor_secret' => $secret])->save();

        return [
            'secret' => $secret,
            'otpauth_uri' => $this->totp->uri($secret, $user->email),
            'issuer' => 'RealityLog',
            'account' => $user->email,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function confirm(User $user, string $code): array
    {
        if (! $user->two_factor_secret || $user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages([
                'code' => __('ui.two_factor.setup_required'),
            ]);
        }

        $step = $this->totp->verify((string) $user->two_factor_secret, $code);

        if ($step === null) {
            throw ValidationException::withMessages([
                'code' => __('ui.two_factor.invalid_code'),
            ]);
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $codes),
            'two_factor_last_used_step' => $step,
        ])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
    }

    /**
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn (): string => Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)))
            ->all();
    }
}
