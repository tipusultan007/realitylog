<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Security\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    private TotpService $totp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'ayesha@example.com',
            'password' => 'password',
        ]);
        UserSetting::create(['user_id' => $this->user->id]);
        $this->token = $this->plainToken($this->user);
        $this->totp = app(TotpService::class);
    }

    public function test_two_factor_can_be_set_up_confirmed_and_stored_encrypted(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/two-factor')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.pending', false);

        $secret = $this->withToken($this->token)
            ->postJson('/api/v1/two-factor/setup')
            ->assertOk()
            ->assertJsonPath('data.issuer', 'RealityLog')
            ->assertJsonPath('data.account', 'ayesha@example.com')
            ->json('data.secret');

        $this->assertNotEmpty($secret);
        $this->assertStringStartsWith('otpauth://totp/', (string) $this->withToken($this->token)
            ->postJson('/api/v1/two-factor/setup')
            ->json('data.otpauth_uri'));

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/two-factor/confirm', [
                'code' => $this->totp->code($secret),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Two-factor authentication is enabled.')
            ->assertJsonCount(8, 'recovery_codes');

        $recoveryCodes = $response->json('recovery_codes');
        $this->assertCount(8, array_unique($recoveryCodes));

        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_confirmed_at);
        $this->assertSame($secret, $this->user->two_factor_secret);
        $this->assertCount(8, $this->user->two_factor_recovery_codes);

        $stored = (string) DB::table('users')->where('id', $this->user->id)->value('two_factor_secret');
        $this->assertNotSame($secret, $stored);
        $this->assertStringNotContainsString($secret, $stored);
    }

    public function test_login_requires_a_second_factor_and_issues_a_token_when_it_is_valid(): void
    {
        $secret = $this->enableTwoFactor();
        $code = $this->totp->code($secret, time() + 30);

        $login = $this->postJson('/api/v1/login', [
            'email' => 'ayesha@example.com',
            'password' => 'password',
            'device_name' => 'Pixel',
        ])->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonMissingPath('token');

        $challenge = (string) $login->json('challenge_token');
        $this->assertNotEmpty($challenge);

        $this->postJson('/api/v1/two-factor/challenge', [
            'challenge_token' => $challenge,
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'ayesha@example.com')
            ->assertJsonPath('user.id', $this->user->id);

        $issued = (string) $this->postJson('/api/v1/login', [
            'email' => 'ayesha@example.com',
            'password' => 'password',
        ])->json('challenge_token');

        $this->assertNotEmpty($issued);

        $this->postJson('/api/v1/two-factor/challenge', [
            'challenge_token' => $challenge,
            'code' => $code,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_token');

        $this->travel(61)->seconds();

        $totpToken = $this->postJson('/api/v1/two-factor/challenge', [
            'challenge_token' => $issued,
            'code' => $this->totp->code($secret),
        ])->assertOk()->json('token');

        $this->withToken($totpToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_an_invalid_code_is_rejected_without_issuing_a_token(): void
    {
        $this->enableTwoFactor();

        $challenge = (string) $this->postJson('/api/v1/login', [
            'email' => 'ayesha@example.com',
            'password' => 'password',
        ])->json('challenge_token');

        $this->postJson('/api/v1/two-factor/challenge', [
            'challenge_token' => $challenge,
            'code' => '000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_a_recovery_code_can_be_used_once(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/two-factor/setup')
            ->assertOk();
        $secret = (string) $response->json('data.secret');

        $recoveryCode = (string) $this->withToken($this->token)
            ->postJson('/api/v1/two-factor/confirm', ['code' => $this->totp->code($secret)])
            ->json('recovery_codes.0');

        $challenge = (string) $this->postJson('/api/v1/login', [
            'email' => 'ayesha@example.com',
            'password' => 'password',
        ])->json('challenge_token');

        $this->postJson('/api/v1/two-factor/challenge', [
            'challenge_token' => $challenge,
            'code' => $recoveryCode,
        ])->assertOk();

        $this->assertCount(7, $this->user->fresh()->two_factor_recovery_codes);

        $secondChallenge = (string) $this->postJson('/api/v1/login', [
            'email' => 'ayesha@example.com',
            'password' => 'password',
        ])->json('challenge_token');

        $this->postJson('/api/v1/two-factor/challenge', [
            'challenge_token' => $secondChallenge,
            'code' => $recoveryCode,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_setup_is_rejected_when_two_factor_is_already_enabled(): void
    {
        $this->enableTwoFactor();

        $this->withToken($this->token)
            ->postJson('/api/v1/two-factor/setup')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Two-factor authentication is already enabled.');
    }

    public function test_disabling_two_factor_requires_the_current_password(): void
    {
        $this->enableTwoFactor();

        $this->withToken($this->token)
            ->postJson('/api/v1/two-factor/disable', ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertNotNull($this->user->fresh()->two_factor_confirmed_at);

        $this->withToken($this->token)
            ->postJson('/api/v1/two-factor/disable', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('message', 'Two-factor authentication is disabled.');

        $this->user->refresh();
        $this->assertNull($this->user->two_factor_secret);
        $this->assertNull($this->user->two_factor_confirmed_at);
        $this->assertNull($this->user->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'two_factor.disabled',
        ]);
    }

    private function enableTwoFactor(): string
    {
        $secret = (string) $this->withToken($this->token)
            ->postJson('/api/v1/two-factor/setup')
            ->assertOk()
            ->json('data.secret');

        $this->withToken($this->token)
            ->postJson('/api/v1/two-factor/confirm', ['code' => $this->totp->code($secret)])
            ->assertOk();

        return $secret;
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
