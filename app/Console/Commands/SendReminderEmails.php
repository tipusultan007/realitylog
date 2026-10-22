<?php

namespace App\Console\Commands;

use App\Mail\ReminderDue;
use App\Models\Reminder;
use App\Models\UserSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendReminderEmails extends Command
{
    protected $signature = 'reminders:send-email {--dry-run : Show what would be sent without sending}';

    protected $description = 'Send due email reminders to users who opted in';

    public function handle(): int
    {
        $sent = 0;
        $skipped = 0;

        Reminder::query()
            ->where('status', 'upcoming')
            ->where('notification_channel', 'email')
            ->whereNull('email_sent_at')
            ->whereBetween('due_at', [now()->subDays(7), now()->addMinutes(5)])
            ->with(['user.settings', 'memory'])
            ->orderBy('due_at')
            ->chunkById(100, function ($reminders) use (&$sent, &$skipped): void {
                foreach ($reminders as $reminder) {
                    $settings = $reminder->user?->settings;

                    if (! $settings
                        || ! $settings->notifications_enabled
                        || ! $settings->email_reminders
                        || $this->inQuietHours($settings)) {
                        $skipped++;

                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("Would send: {$reminder->title} to {$reminder->user->email}");
                        $sent++;

                        continue;
                    }

                    Mail::to($reminder->user->email)->send(new ReminderDue($reminder));
                    $reminder->forceFill(['email_sent_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("Email reminders sent: {$sent}; skipped: {$skipped}.");

        return self::SUCCESS;
    }

    private function inQuietHours(UserSetting $settings): bool
    {
        if (! $settings->quiet_hours_start || ! $settings->quiet_hours_end) {
            return false;
        }

        $timezone = $settings->timezone ?: config('app.timezone');
        $now = Carbon::now($timezone);
        $start = Carbon::parse($now->toDateString().' '.$settings->quiet_hours_start, $timezone);
        $end = Carbon::parse($now->toDateString().' '.$settings->quiet_hours_end, $timezone);

        return $start->greaterThan($end)
            ? $now->greaterThanOrEqualTo($start) || $now->lessThan($end)
            : $now->between($start, $end);
    }
}
