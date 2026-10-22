<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserSettingResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $user->settings()->firstOrCreate([], [
            'interface_locale' => app()->getLocale(),
            'ai_locale' => app()->getLocale(),
        ]);

        $user->setRelation('settings', $settings);

        return response()->json([
            'user' => UserResource::make($user)->resolve(),
            'settings' => UserSettingResource::make($settings)->resolve(),
            'meta' => [
                'date_formats' => ['d M Y', 'j F Y', 'd/m/Y', 'Y-m-d'],
                'languages' => [
                    ['value' => 'en', 'label' => __('ui.languages.en')],
                    ['value' => 'bn', 'label' => __('ui.languages.bn')],
                ],
                'themes' => ['light', 'dark', 'system'],
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone:all'],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $request->user()->update(['name' => $validated['name']]);
        $settings = $request->user()->settings()->firstOrCreate([]);
        $settings->update([
            'timezone' => $validated['timezone'],
            'country' => mb_strtoupper($validated['country']),
            'currency' => mb_strtoupper($validated['currency']),
        ]);
        $request->user()->setRelation('settings', $settings);

        $this->audit($request, 'settings.profile_updated');

        return response()->json([
            'message' => __('ui.settings.saved'),
            'user' => UserResource::make($request->user()->load('settings'))->resolve(),
        ]);
    }

    public function updateLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'interface_locale' => ['required', Rule::in(['en', 'bn'])],
            'ai_locale' => ['required', Rule::in(['en', 'bn'])],
            'date_format' => ['required', Rule::in(['d M Y', 'j F Y', 'd/m/Y', 'Y-m-d'])],
        ]);

        $settings = $request->user()->settings()->firstOrCreate([]);
        $settings->update($validated);
        $request->user()->setRelation('settings', $settings);

        App::setLocale($validated['interface_locale']);
        Carbon::setLocale($validated['interface_locale']);

        $this->audit($request, 'settings.language_updated');

        return response()->json([
            'message' => __('ui.settings.saved'),
            'settings' => UserSettingResource::make($settings)->resolve(),
        ]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notifications_enabled' => ['nullable', 'boolean'],
            'email_reminders' => ['nullable', 'boolean'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
        ]);

        $settings = $request->user()->settings()->firstOrCreate([]);
        $settings->update([
            'notifications_enabled' => $request->boolean('notifications_enabled'),
            'email_reminders' => $request->boolean('email_reminders'),
            'quiet_hours_start' => $validated['quiet_hours_start'] ?? null,
            'quiet_hours_end' => $validated['quiet_hours_end'] ?? null,
        ]);
        $request->user()->setRelation('settings', $settings);

        $this->audit($request, 'settings.notifications_updated');

        return response()->json([
            'message' => __('ui.settings.saved'),
            'settings' => UserSettingResource::make($settings)->resolve(),
        ]);
    }

    public function updatePrivacy(Request $request): JsonResponse
    {
        $request->validate([
            'external_ai_consent' => ['nullable', 'boolean'],
        ]);

        $settings = $request->user()->settings()->firstOrCreate([]);
        $settings->update([
            'external_ai_consent' => $request->boolean('external_ai_consent'),
        ]);
        $request->user()->setRelation('settings', $settings);

        $this->audit($request, 'settings.privacy_updated');

        return response()->json([
            'message' => __('ui.settings.saved'),
            'settings' => UserSettingResource::make($settings)->resolve(),
        ]);
    }

    public function destroyAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', Rule::in(['DELETE'])],
        ]);

        $user = $request->user();

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

        return response()->json([
            'message' => __('ui.api.account_deleted'),
        ]);
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
