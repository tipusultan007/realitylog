<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:send-email')->hourly()->withoutOverlapping();
Schedule::command('backup:database')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('audit:prune')->monthlyOn(1, '03:00')->withoutOverlapping();
Schedule::command('attachments:prune-orphans')->weekly()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->weekly()->withoutOverlapping();
