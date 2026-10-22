<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_a_reminder_can_be_created_for_an_owned_memory(): void
    {
        $memory = $this->createMemory($this->user);

        $this->actingAs($this->user)
            ->post(route('reminders.store'), [
                'title' => 'Renew the flat rent agreement',
                'due_at' => now()->addDays(10)->toDateTimeString(),
                'type' => 'renewal',
                'memory_id' => $memory->id,
                'notes' => 'Bring the previous agreement.',
            ])
            ->assertSessionHasNoErrors();

        $reminder = Reminder::sole();

        $this->assertSame($this->user->id, $reminder->user_id);
        $this->assertSame($memory->id, $reminder->memory_id);
        $this->assertSame('upcoming', $reminder->status);
        $this->assertSame('in_app', $reminder->notification_channel);
    }

    public function test_a_reminder_cannot_link_to_another_users_memory_or_be_past_dated(): void
    {
        $foreignMemory = $this->createMemory(User::factory()->create());

        $this->actingAs($this->user)
            ->post(route('reminders.store'), [
                'title' => 'Sneaky reminder',
                'due_at' => now()->addDay()->toDateTimeString(),
                'type' => 'custom',
                'memory_id' => $foreignMemory->id,
            ])
            ->assertSessionHasErrors('memory_id');

        $this->actingAs($this->user)
            ->post(route('reminders.store'), [
                'title' => 'Too late',
                'due_at' => now()->subDay()->toDateTimeString(),
                'type' => 'custom',
            ])
            ->assertSessionHasErrors('due_at');

        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_owner_can_complete_and_snooze_a_reminder(): void
    {
        $reminder = $this->createReminder($this->user, now()->addDays(3));

        $this->actingAs($this->user)
            ->patch(route('reminders.complete', $reminder))
            ->assertSessionHasNoErrors();

        $reminder->refresh();

        $this->assertSame('completed', $reminder->status);
        $this->assertNotNull($reminder->completed_at);

        $this->actingAs($this->user)
            ->patch(route('reminders.snooze', $reminder))
            ->assertSessionHasNoErrors();

        $reminder->refresh();

        $this->assertSame('upcoming', $reminder->status);
        $this->assertTrue($reminder->due_at->gt(now()->addDays(6)));
    }

    public function test_other_users_cannot_touch_a_reminder(): void
    {
        $reminder = $this->createReminder($this->user, now()->addDays(3));
        $intruder = User::factory()->create();

        $this->actingAs($intruder);

        $this->patch(route('reminders.complete', $reminder))->assertForbidden();
        $this->patch(route('reminders.snooze', $reminder))->assertForbidden();
        $this->delete(route('reminders.destroy', $reminder))->assertForbidden();

        $this->assertDatabaseHas('reminders', ['id' => $reminder->id, 'status' => 'upcoming']);
    }

    public function test_the_reminders_page_groups_overdue_upcoming_and_completed_items(): void
    {
        $this->createReminder($this->user, now()->subDay(), 'Overdue renewal');
        $this->createReminder($this->user, now()->addDays(2), 'Upcoming renewal');

        $this->actingAs($this->user)
            ->get(route('reminders.index'))
            ->assertOk()
            ->assertSee('Overdue renewal')
            ->assertSee('Upcoming renewal');
    }

    private function createMemory(User $user): Memory
    {
        return Memory::create([
            'user_id' => $user->id,
            'title' => 'Rent agreement record',
            'memory_type' => 'document',
            'source_type' => 'text',
            'detected_language' => 'en',
            'captured_at' => now(),
            'processing_status' => 'ready',
        ]);
    }

    private function createReminder(User $user, $dueAt, string $title = 'Follow up'): Reminder
    {
        return Reminder::create([
            'user_id' => $user->id,
            'title' => $title,
            'type' => 'follow_up',
            'due_at' => $dueAt,
            'status' => 'upcoming',
            'notification_channel' => 'in_app',
        ]);
    }
}
