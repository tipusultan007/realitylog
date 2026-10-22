<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Security\TwoFactorAuthenticator;
use App\Services\Security\TwoFactorChallenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('two_factor_token')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function store(
        Request $request,
        TwoFactorChallenge $challenge,
        TwoFactorAuthenticator $authenticator,
    ): RedirectResponse {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $token = (string) $request->session()->get('two_factor_token');

        try {
            [$user, , $nonce] = $challenge->consume($token);
        } catch (ValidationException) {
            $request->session()->forget(['two_factor_token', 'two_factor_remember']);

            return redirect()->route('login')->withErrors([
                'email' => __('ui.two_factor.challenge_invalid'),
            ]);
        }

        if (! $authenticator->verify($user, (string) $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => __('ui.two_factor.invalid_code'),
            ]);
        }

        $challenge->markUsed($nonce);
        $remember = (bool) $request->session()->pull('two_factor_remember', false);

        Auth::login($user, $remember);
        $request->session()->forget('two_factor_token');
        $request->session()->regenerate();
        $request->session()->put('locale', $user->settings?->interface_locale ?? 'en');

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'web.login',
            'meta' => ['two_factor' => true],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['two_factor_token', 'two_factor_remember']);

        return redirect()->route('login');
    }
}
