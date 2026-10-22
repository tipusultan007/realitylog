<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSetting;
use App\Services\Security\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_two_factor_enabled_is_challenged_before_login(): void
    {
        $secret = app(TotpService::class)->generateSecret();
        $user = User::factory()->create([
            'password' => 'password',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);
        UserSetting::create(['user_id' => $user->id]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();

        $this->get(route('two-factor.challenge'))->assertOk();

        $this->post(route('two-factor.verify'), [
            'code' => app(TotpService::class)->code($secret),
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_recovery_code_can_complete_the_web_challenge_once(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'two_factor_secret' => app(TotpService::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('ABCD-EFGH')],
        ]);
        UserSetting::create(['user_id' => $user->id]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->post(route('two-factor.verify'), ['code' => 'abcd-efgh'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);
    }

    public function test_two_factor_can_be_enabled_and_disabled_from_settings(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        UserSetting::create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('settings.two-factor.show'))
            ->assertOk()
            ->assertSee(__('ui.settings.mfa_secret'));

        $secret = (string) $user->fresh()->two_factor_secret;

        $this->actingAs($user)
            ->post(route('settings.two-factor.confirm'), [
                'code' => app(TotpService::class)->code($secret),
            ])
            ->assertRedirect(route('settings.two-factor.show'))
            ->assertSessionHas('two_factor_recovery_codes');

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $this->actingAs($user)
            ->delete(route('settings.two-factor.destroy'), ['password' => 'password'])
            ->assertRedirect(route('settings.two-factor.show'));

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_an_invalid_two_factor_code_does_not_sign_the_user_in(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'two_factor_secret' => app(TotpService::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ]);
        UserSetting::create(['user_id' => $user->id]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->post(route('two-factor.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }
}
