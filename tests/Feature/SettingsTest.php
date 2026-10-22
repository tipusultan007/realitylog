<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_settings_update_the_user_and_their_locale_preferences(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)
            ->patch(route('settings.profile'), [
                'name' => 'Ayesha Rahman',
                'timezone' => 'Asia/Dhaka',
                'country' => 'bd',
                'currency' => 'bdt',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Ayesha Rahman', $user->fresh()->name);

        $settings = $user->settings()->firstOrFail();
        $this->assertSame('Asia/Dhaka', $settings->timezone);
        $this->assertSame('BD', $settings->country);
        $this->assertSame('BDT', $settings->currency);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'settings.profile_updated',
        ]);
    }

    public function test_notification_preferences_can_be_enabled_and_disabled(): void
    {
        $user = User::factory()->create();
        UserSetting::create([
            'user_id' => $user->id,
            'notifications_enabled' => false,
            'email_reminders' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('settings.notifications'), [
                'notifications_enabled' => '1',
                'email_reminders' => '1',
                'quiet_hours_start' => '22:30',
                'quiet_hours_end' => '06:30',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $settings = $user->settings()->firstOrFail();
        $this->assertTrue($settings->notifications_enabled);
        $this->assertTrue($settings->email_reminders);
        $this->assertSame('22:30', $settings->quiet_hours_start);
        $this->assertSame('06:30', $settings->quiet_hours_end);

        $this->actingAs($user)
            ->patch(route('settings.notifications'), [])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $settings = $user->settings()->firstOrFail();
        $this->assertFalse($settings->notifications_enabled);
        $this->assertFalse($settings->email_reminders);
        $this->assertNull($settings->quiet_hours_start);
        $this->assertNull($settings->quiet_hours_end);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'settings.notifications_updated',
        ]);
    }

    public function test_privacy_consent_can_be_granted_and_withdrawn(): void
    {
        $user = User::factory()->create();
        UserSetting::create([
            'user_id' => $user->id,
            'external_ai_consent' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('settings.privacy'), ['external_ai_consent' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->settings()->firstOrFail()->external_ai_consent);

        $this->actingAs($user)
            ->patch(route('settings.privacy'), [])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($user->settings()->firstOrFail()->external_ai_consent);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'settings.privacy_updated',
        ]);
    }
}
