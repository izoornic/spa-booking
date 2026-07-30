<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The overlap mutex gets an explicit expiry (in minutes). The default is 24h, so a
 * process killed mid-run would otherwise block its own command for a whole day.
 */
Schedule::command('spa:refresh-permanents')->everyFifteenMinutes()->withoutOverlapping(20);
Schedule::command('spa:posalji-podsetnike')->hourly();

/*
 * Shared hosting has no supervisor, so the scheduler drains the queue itself:
 * one cron entry (schedule:run every minute) drives everything. --max-time
 * stays under a minute so the next tick never overlaps this one.
 */
Schedule::command('queue:work --stop-when-empty --max-time=55')
    ->everyMinute()
    ->withoutOverlapping(2);
