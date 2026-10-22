<?php

namespace Tests\Feature;

use App\Mail\ReminderDue;
use App\Models\Reminder;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_email_reminders_are_sent_and_marked(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        UserSetting::create([
            'user_id' => $user->id,
            'notifications_enabled' => true,
            'email_reminders' => true,
        ]);

        $reminder = Reminder::create([
            'user_id' => $user->id,
            'title' => 'Check the warranty',
            'type' => 'warranty',
            'due_at' => now()->subMinute(),
            'status' => 'upcoming',
            'notification_channel' => 'email',
        ]);

        $this->artisan('reminders:send-email')->assertExitCode(0);

        Mail::assertSent(ReminderDue::class, fn (ReminderDue $mail): bool => $mail->reminder->is($reminder));
        $this->assertNotNull($reminder->fresh()->email_sent_at);
    }

    public function test_users_who_opted_out_do_not_receive_email_reminders(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        UserSetting::create([
            'user_id' => $user->id,
            'notifications_enabled' => true,
            'email_reminders' => false,
        ]);

        Reminder::create([
            'user_id' => $user->id,
            'title' => 'Do not email me',
            'type' => 'other',
            'due_at' => now()->subMinute(),
            'status' => 'upcoming',
            'notification_channel' => 'email',
        ]);

        $this->artisan('reminders:send-email')->assertExitCode(0);

        Mail::assertNothingSent();
    }
}
