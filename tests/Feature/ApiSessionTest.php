<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_are_listed_with_the_current_device_marked(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id]);

        $current = $this->plainToken($user, 'Current phone', now()->subMinutes(2));
        $this->plainToken($user, 'Old tablet', now()->subDay());
        $otherToken = $this->tokenNamed($user, 'Old tablet');
        $currentToken = $this->tokenNamed($user, 'Current phone');

        $this->withToken($current)
            ->getJson('/api/v1/sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $currentToken->id)
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.0.name', 'Current phone')
            ->assertJsonPath('data.1.id', $otherToken->id)
            ->assertJsonPath('data.1.is_current', false);
    }

    public function test_a_device_can_revoke_another_session_without_ending_its_own(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id]);

        $current = $this->plainToken($user, 'Phone');
        $this->plainToken($user, 'Tablet');
        $other = $this->tokenNamed($user, 'Tablet');

        $this->withToken($current)
            ->deleteJson("/api/v1/sessions/{$other->id}")
            ->assertOk()
            ->assertJsonPath('revoked_current', false)
            ->assertJsonPath('message', 'Device signed out.');

        $this->assertDatabaseMissing('api_tokens', ['id' => $other->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'session.revoked',
            'subject_id' => $other->id,
        ]);

        $this->withToken($current)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_revoking_the_current_session_is_reported_as_signing_out(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id]);

        $current = $this->plainToken($user, 'Phone');
        $currentToken = $this->tokenNamed($user, 'Phone');

        $this->withToken($current)
            ->deleteJson("/api/v1/sessions/{$currentToken->id}")
            ->assertOk()
            ->assertJsonPath('revoked_current', true)
            ->assertJsonPath('message', 'Signed out.');

        $this->withToken($current)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_a_user_cannot_revoke_another_users_session(): void
    {
        $owner = User::factory()->create();
        UserSetting::create(['user_id' => $owner->id]);
        $this->plainToken($owner, 'Owner phone');
        $foreign = $this->tokenNamed($owner, 'Owner phone');

        $intruder = User::factory()->create();
        UserSetting::create(['user_id' => $intruder->id]);
        $intruderToken = $this->plainToken($intruder, 'Intruder phone');

        $this->withToken($intruderToken)
            ->deleteJson("/api/v1/sessions/{$foreign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('api_tokens', ['id' => $foreign->id]);
    }

    public function test_other_sessions_can_be_revoked_in_one_request(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id]);

        $current = $this->plainToken($user, 'Phone');
        $this->plainToken($user, 'Tablet');
        $this->plainToken($user, 'Laptop');

        $this->withToken($current)
            ->deleteJson('/api/v1/sessions')
            ->assertOk()
            ->assertJsonPath('revoked', 2)
            ->assertJsonPath('message', 'All other devices signed out.');

        $this->assertDatabaseCount('api_tokens', 1);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'session.revoked_others',
        ]);

        $this->withToken($current)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    private function plainToken(User $user, string $name, ?\DateTimeInterface $lastUsedAt = null): string
    {
        $plain = 'rl_'.Str::random(40);

        ApiToken::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'last_used_at' => $lastUsedAt,
            'expires_at' => now()->addDays(90),
        ]);

        return $plain;
    }

    private function tokenNamed(User $user, string $name): ApiToken
    {
        return $user->apiTokens()->where('name', $name)->sole();
    }
}
