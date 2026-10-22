<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_password_reset_link_can_be_requested_and_used(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'old-password1',
        ]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        $token = null;

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password2',
            'password_confirmation' => 'new-password2',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password2', $user->fresh()->password));
    }

    public function test_an_invalid_reset_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->post(route('password.store'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password2',
            'password_confirmation' => 'new-password2',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_forms_render(): void
    {
        $this->get(route('password.request'))->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee(__('ui.settings.change_password'));
    }

    public function test_a_signed_in_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password1']);

        $this->actingAs($user)
            ->patch(route('settings.password'), [
                'current_password' => 'old-password1',
                'password' => 'new-password2',
                'password_confirmation' => 'new-password2',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password2', $user->fresh()->password));
    }

    public function test_changing_a_password_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password1']);

        $this->actingAs($user)
            ->patch(route('settings.password'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password2',
                'password_confirmation' => 'new-password2',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password1', $user->fresh()->password));
    }
}
