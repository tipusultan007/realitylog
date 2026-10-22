<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_sign_in_and_use_the_issued_bearer_token(): void
    {
        $user = User::factory()->create(['email' => 'ayesha@example.com', 'password' => 'password']);
        UserSetting::create([
            'user_id' => $user->id,
            'interface_locale' => 'bn',
            'ai_locale' => 'bn',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'ayesha@example.com',
            'password' => 'password',
            'device_name' => 'pixel-test',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'ayesha@example.com')
            ->assertJsonPath('user.settings.interface_locale', 'bn');

        $token = $response->json('token');

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $user->id,
            'name' => 'pixel-test',
            'token_hash' => hash('sha256', $token),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ayesha@example.com');
    }

    public function test_registration_creates_bilingual_settings_and_an_authenticated_session(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Nusrat Jahan',
            'email' => 'Nusrat@Example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'locale' => 'bn',
            'device_name' => 'android',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Nusrat Jahan')
            ->assertJsonPath('user.email', 'nusrat@example.com')
            ->assertJsonPath('user.settings.interface_locale', 'bn')
            ->assertJsonPath('user.settings.ai_locale', 'bn');

        $user = User::where('email', 'nusrat@example.com')->sole();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'account.created',
        ]);

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.memories', 0);
    }

    public function test_registration_rejects_duplicate_emails_and_weak_passwords(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/register', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'locale' => 'en',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_invalid_credentials_and_missing_tokens_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->getJson('/api/v1/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Please sign in to continue.');

        $this->withToken('not-a-real-token')
            ->getJson('/api/v1/dashboard')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_token_used_for_the_request(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id]);

        $first = $this->plainToken($user);
        $second = $this->plainToken($user);

        $this->withToken($first)
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Signed out.');

        $this->assertDatabaseCount('api_tokens', 1);

        $this->withToken($first)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();

        $this->withToken($second)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $plain = 'rl_'.Str::random(40);

        ApiToken::create([
            'user_id' => $user->id,
            'name' => 'expired',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken($plain)->getJson('/api/v1/me')->assertUnauthorized();
    }

    private function plainToken(User $user): string
    {
        $plain = 'rl_'.Str::random(40);

        ApiToken::create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
