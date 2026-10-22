<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_and_receives_bilingual_settings(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Nusrat Jahan',
            'email' => 'nusrat@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'locale' => 'bn',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticated();

        $user = User::where('email', 'nusrat@example.com')->sole();

        $this->assertNull($user->email_verified_at);
        $this->assertSame('bn', $user->settings->interface_locale);
        $this->assertSame('bn', $user->settings->ai_locale);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'account.created']);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_validates_unique_email_and_password_strength(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'locale' => 'en',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_a_user_can_sign_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        UserSetting::create(['user_id' => $user->id, 'interface_locale' => 'bn']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('bn', session('locale'));

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_account_deletion_requires_the_password_and_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $memory = Memory::create([
            'user_id' => $user->id,
            'title' => 'A memory to delete',
            'memory_type' => 'note',
            'source_type' => 'text',
            'detected_language' => 'en',
            'captured_at' => now(),
            'processing_status' => 'ready',
        ]);

        $this->actingAs($user)
            ->delete(route('settings.account.destroy'), [
                'password' => 'wrong-password',
                'confirmation' => 'DELETE',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('settings.account.destroy'), [
                'password' => 'password',
                'confirmation' => 'DELETE',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('memories', ['id' => $memory->id]);
    }
}
