<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $settings = $request->user()->settings()->firstOrCreate([], [
            'interface_locale' => app()->getLocale(),
            'ai_locale' => app()->getLocale(),
        ]);

        return view('settings.edit', [
            'settings' => $settings,
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone:all'],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $request->user()->update(['name' => $validated['name']]);
        $request->user()->settings()->firstOrCreate([])->update([
            'timezone' => $validated['timezone'],
            'country' => mb_strtoupper($validated['country']),
            'currency' => mb_strtoupper($validated['currency']),
        ]);

        $this->audit($request, 'settings.profile_updated');

        return back()->with('status', __('ui.settings.saved'));
    }

    public function updateLanguage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'interface_locale' => ['required', Rule::in(['en', 'bn'])],
            'ai_locale' => ['required', Rule::in(['en', 'bn'])],
            'date_format' => ['required', Rule::in(['d M Y', 'j F Y', 'd/m/Y', 'Y-m-d'])],
        ]);

        $request->user()->settings()->firstOrCreate([])->update($validated);
        $request->user()->unsetRelation('settings');
        $request->session()->put('locale', $validated['interface_locale']);

        $this->audit($request, 'settings.language_updated');

        return back()->with('status', __('ui.settings.saved'));
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notifications_enabled' => ['nullable', 'boolean'],
            'email_reminders' => ['nullable', 'boolean'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
        ]);

        $request->user()->settings()->firstOrCreate([])->update([
            'notifications_enabled' => $request->boolean('notifications_enabled'),
            'email_reminders' => $request->boolean('email_reminders'),
            'quiet_hours_start' => $validated['quiet_hours_start'] ?? null,
            'quiet_hours_end' => $validated['quiet_hours_end'] ?? null,
        ]);

        $this->audit($request, 'settings.notifications_updated');

        return back()->with('status', __('ui.settings.saved'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $validated['password']]);
        $request->user()->apiTokens()->delete();
        $request->session()->regenerate();

        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $this->audit($request, 'settings.password_updated');

        return back()->with('status', __('ui.settings.password_changed'));
    }

    public function updatePrivacy(Request $request): RedirectResponse
    {
        $request->validate([
            'external_ai_consent' => ['nullable', 'boolean'],
        ]);

        $request->user()->settings()->firstOrCreate([])->update([
            'external_ai_consent' => $request->boolean('external_ai_consent'),
        ]);

        $this->audit($request, 'settings.privacy_updated');

        return back()->with('status', __('ui.settings.saved'));
    }

    public function destroyAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', Rule::in(['DELETE'])],
        ]);

        $user = $request->user();

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        DB::transaction(function () use ($user): void {
            $user->memories()->with('attachments')->get()->each(function ($memory): void {
                foreach ($memory->attachments as $attachment) {
                    if ($attachment->disk === 'local') {
                        Storage::disk('local')->delete($attachment->path);
                    }
                }
            });

            $user->delete();
        });

        return redirect()->route('home');
    }

    private function audit(Request $request, string $action): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'ip_address' => $request->ip(),
        ]);
    }
}
