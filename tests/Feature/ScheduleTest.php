<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Production runs a single cron entry (`schedule:run` every minute), so every
 * recurring job must be registered here or it silently never runs.
 */
class ScheduleTest extends TestCase
{
    /**
     * @return Collection<int, Event>
     */
    private function events(): Collection
    {
        return collect(app(Schedule::class)->events());
    }

    private function find(string $command): Event
    {
        $event = $this->events()->first(
            fn (Event $e): bool => str_contains($e->command ?? '', $command)
        );

        $this->assertNotNull($event, "Komanda nije u scheduleru: {$command}");

        return $event;
    }

    public function test_permanent_refresh_runs_every_fifteen_minutes_without_overlapping(): void
    {
        $event = $this->find('spa:refresh-permanents');

        $this->assertSame('*/15 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);

        // Not the 24h default: a killed run must not block the command for a day.
        $this->assertSame(20, $event->expiresAt);
    }

    public function test_reminders_run_hourly(): void
    {
        $this->assertSame('0 * * * *', $this->find('spa:posalji-podsetnike')->expression);
    }

    public function test_queue_is_drained_every_minute_so_queued_mail_is_delivered(): void
    {
        $event = $this->find('queue:work');

        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(2, $event->expiresAt);

        // Must exit on its own; a plain `queue:work` would hang the cron tick forever.
        $this->assertStringContainsString('--stop-when-empty', $event->command);
        $this->assertStringContainsString('--max-time=55', $event->command);
    }

    public function test_app_timezone_is_configurable_from_the_environment(): void
    {
        // Spa termini are wall-clock times, so production must override the UTC default.
        $this->assertSame('Europe/Belgrade', config('app.timezone'));
        $this->assertSame('Europe/Belgrade', date_default_timezone_get());
    }
}
