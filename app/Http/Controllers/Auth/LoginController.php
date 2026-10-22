<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\TwoFactorChallenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request, TwoFactorChallenge $challenge): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('ui.auth.failed'),
            ]);
        }

        if ($user->two_factor_confirmed_at !== null) {
            $pending = $challenge->create($user, 'web');

            $request->session()->put([
                'two_factor_token' => $pending['token'],
                'two_factor_remember' => $request->boolean('remember'),
            ]);

            return redirect()->route('two-factor.challenge');
        }

        auth()->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('locale', $user->settings?->interface_locale ?? 'en');

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
