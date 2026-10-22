<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\Security\TwoFactorManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TwoFactorSettingsController extends Controller
{
    public function show(Request $request, TwoFactorManager $manager): View
    {
        $user = $request->user()->load('settings');
        $setup = null;

        if ($user->two_factor_confirmed_at === null) {
            $setup = $manager->begin($user);
        }

        return view('settings.two-factor', [
            'enabled' => $user->two_factor_confirmed_at !== null,
            'setup' => $setup,
            'recoveryCodes' => (array) $request->session()->get('two_factor_recovery_codes', []),
        ]);
    }

    public function confirm(Request $request, TwoFactorManager $manager): RedirectResponse
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

        return redirect()->route('settings.two-factor.show')
            ->with('status', __('ui.two_factor.enabled'))
            ->with('two_factor_recovery_codes', $codes);
    }

    public function destroy(Request $request, TwoFactorManager $manager): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $manager->disable($request->user());

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'two_factor.disabled',
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('settings.two-factor.show')
            ->with('status', __('ui.two_factor.disabled'));
    }
}
