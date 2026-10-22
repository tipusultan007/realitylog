<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Collection;
use App\Models\Memory;
use App\Models\Reminder;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiReminderSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        UserSetting::create([
            'user_id' => $this->user->id,
            'interface_locale' => 'en',
            'ai_locale' => 'en',
        ]);

        $this->token = $this->plainToken($this->user);
    }

    public function test_reminders_can_be_created_completed_snoozed_and_listed(): void
    {
        $memory = $this->createMemory(['title' => 'Rent agreement']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/reminders', [
                'title' => 'Renew the flat rent agreement',
                'due_at' => now()->addDays(10)->toDateTimeString(),
                'type' => 'renewal',
                'memory_id' => $memory->id,
                'notes' => 'Bring the previous agreement.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'upcoming')
            ->assertJsonPath('data.memory.id', $memory->id);

        $reminder = Reminder::sole();

        $this->withToken($this->token)
            ->getJson('/api/v1/reminders')
            ->assertOk()
            ->assertJsonCount(1, 'upcoming')
            ->assertJsonPath('upcoming.0.id', $reminder->id);

        $this->withToken($this->token)
            ->patchJson('/api/v1/reminders/'.$reminder->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $reminder->refresh();
        $this->assertNotNull($reminder->completed_at);

        $this->withToken($this->token)
            ->patchJson('/api/v1/reminders/'.$reminder->id.'/snooze')
            ->assertOk()
            ->assertJsonPath('data.status', 'upcoming');

        $reminder->refresh();
        $this->assertTrue($reminder->due_at->gt(now()->addDays(6)));
    }

    public function test_reminders_cannot_link_to_foreign_memories_or_be_touched_by_other_users(): void
    {
        $foreignMemory = $this->createMemory([
            'title' => 'Foreign memory',
            'user_id' => User::factory()->create()->id,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/reminders', [
                'title' => 'Sneaky reminder',
                'due_at' => now()->addDay()->toDateTimeString(),
                'type' => 'custom',
                'memory_id' => $foreignMemory->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('memory_id');

        $reminder = Reminder::create([
            'user_id' => $this->user->id,
            'title' => 'Private reminder',
            'type' => 'custom',
            'due_at' => now()->addDay(),
            'status' => 'upcoming',
            'notification_channel' => 'in_app',
        ]);

        $intruder = User::factory()->create();
        $intruderToken = $this->plainToken($intruder);

        $this->withToken($intruderToken)
            ->patchJson('/api/v1/reminders/'.$reminder->id.'/complete')
            ->assertForbidden();

        $this->withToken($intruderToken)
            ->deleteJson('/api/v1/reminders/'.$reminder->id)
            ->assertForbidden();
    }

    public function test_settings_can_be_read_and_updated_independently(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('settings.interface_locale', 'en')
            ->assertJsonPath('settings.ai_locale', 'en');

        $this->withToken($this->token)
            ->patchJson('/api/v1/settings/profile', [
                'name' => 'Ayesha Rahman',
                'timezone' => 'Asia/Dhaka',
                'country' => 'bd',
                'currency' => 'bdt',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Ayesha Rahman')
            ->assertJsonPath('user.settings.country', 'BD');

        $this->withToken($this->token)
            ->patchJson('/api/v1/settings/language', [
                'interface_locale' => 'bn',
                'ai_locale' => 'en',
                'date_format' => 'j F Y',
            ])
            ->assertOk()
            ->assertJsonPath('settings.interface_locale', 'bn')
            ->assertJsonPath('settings.ai_locale', 'en');

        $this->withToken($this->token)
            ->patchJson('/api/v1/settings/notifications', [
                'notifications_enabled' => true,
                'email_reminders' => false,
                'quiet_hours_start' => '22:30',
                'quiet_hours_end' => '06:30',
            ])
            ->assertOk()
            ->assertJsonPath('settings.email_reminders', false)
            ->assertJsonPath('settings.quiet_hours_start', '22:30');

        $this->withToken($this->token)
            ->patchJson('/api/v1/settings/privacy', [
                'external_ai_consent' => true,
            ])
            ->assertOk()
            ->assertJsonPath('settings.external_ai_consent', true);
    }

    public function test_account_deletion_removes_the_user_records_and_tokens(): void
    {
        $memory = $this->createMemory(['title' => 'A memory to delete']);

        $this->withToken($this->token)
            ->deleteJson('/api/v1/account', [
                'password' => 'wrong-password',
                'confirmation' => 'DELETE',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseHas('users', ['id' => $this->user->id]);

        $this->withToken($this->token)
            ->deleteJson('/api/v1/account', [
                'password' => 'password',
                'confirmation' => 'DELETE',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
        $this->assertDatabaseMissing('memories', ['id' => $memory->id]);
        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_timeline_groups_memories_by_their_event_date(): void
    {
        $this->createMemory([
            'title' => 'September appointment',
            'event_date' => '2026-09-12',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/timeline?month=2026-09');

        $response->assertOk()
            ->assertJsonPath('month', '2026-09')
            ->assertJsonPath('month_total', 1)
            ->assertJsonPath('groups.0.date', '2026-09-12')
            ->assertJsonPath('groups.0.memories.0.title', 'September appointment');
    }

    public function test_export_returns_a_downloadable_json_archive(): void
    {
        $this->createMemory(['title' => 'Exported memory']);

        $response = $this->withToken($this->token)
            ->get('/api/v1/export');

        $response->assertOk()
            ->assertJsonPath('export.format', 'realitylog-json-v1')
            ->assertJsonPath('export.record_count', 1)
            ->assertJsonPath('memories.0.title', 'Exported memory');

        $this->assertStringContainsString(
            'attachment; filename="realitylog-export-',
            (string) $response->headers->get('Content-Disposition')
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'data.exported']);
    }

    public function test_collections_can_be_listed_and_created(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/collections', [
                'name' => 'Important documents',
                'description' => 'Warranties and agreements',
                'color' => '#517A64',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Important documents');

        $this->withToken($this->token)
            ->getJson('/api/v1/collections')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.memories_count', 0);
    }

    public function test_collections_can_be_renamed_and_deleted(): void
    {
        $collection = Collection::create([
            'user_id' => $this->user->id,
            'name' => 'Old name',
            'color' => '#D95F3B',
        ]);

        $this->withToken($this->token)
            ->patchJson('/api/v1/collections/'.$collection->id, ['name' => 'Home records'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Home records')
            ->assertJsonPath('data.memories_count', 0);

        $this->withToken($this->token)
            ->deleteJson('/api/v1/collections/'.$collection->id)
            ->assertOk();

        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
    }

    public function test_other_users_cannot_rename_or_delete_collections(): void
    {
        $collection = Collection::create([
            'user_id' => $this->user->id,
            'name' => 'Private collection',
            'color' => '#D95F3B',
        ]);

        $intruder = User::factory()->create();

        $this->withToken($this->plainToken($intruder))
            ->patchJson('/api/v1/collections/'.$collection->id, ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->withToken($this->plainToken($intruder))
            ->deleteJson('/api/v1/collections/'.$collection->id)
            ->assertNotFound();

        $this->assertDatabaseHas('collections', ['id' => $collection->id, 'name' => 'Private collection']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMemory(array $attributes): Memory
    {
        return Memory::create([
            'user_id' => $attributes['user_id'] ?? $this->user->id,
            'title' => $attributes['title'],
            'memory_type' => $attributes['memory_type'] ?? 'note',
            'source_type' => 'text',
            'detected_language' => 'en',
            'event_date' => $attributes['event_date'] ?? null,
            'captured_at' => now(),
            'processing_status' => 'ready',
        ]);
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
