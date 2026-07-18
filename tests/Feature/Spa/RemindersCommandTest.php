<?php

namespace Tests\Feature\Spa;

use App\Mail\RezervacijaPodsetnik;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class RemindersCommandTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    public function test_reminder_is_sent_within_the_window(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->today(), 1, 2);

        // Slot 1 starts 12:00; move to 10:30 so it falls in the 3h reminder window.
        $this->travelTo(CarbonImmutable::parse('2026-08-03 10:30:00'));

        $this->artisan('spa:posalji-podsetnike')->assertSuccessful();

        Mail::assertQueued(RezervacijaPodsetnik::class);
        $this->assertNotNull($booking->fresh()->podsetnik_poslat_at);
    }

    public function test_reminder_is_not_sent_before_the_window(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        // At 08:00 the slot 1 start (12:00) is more than 3h away.
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->today(), 1, 2);

        $this->artisan('spa:posalji-podsetnike')->assertSuccessful();

        Mail::assertNotQueued(RezervacijaPodsetnik::class);
    }

    public function test_reminder_is_sent_only_once(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->today(), 1, 2);

        $this->travelTo(CarbonImmutable::parse('2026-08-03 10:30:00'));

        $this->artisan('spa:posalji-podsetnike')->assertSuccessful();
        $this->artisan('spa:posalji-podsetnike')->assertSuccessful();

        Mail::assertQueued(RezervacijaPodsetnik::class, 1);
    }

    public function test_reminders_disabled_when_hours_is_zero(): void
    {
        Mail::fake();
        $this->bootScenario(['podsetnik_sati_pre' => 0]);
        $stan = $this->stan();
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->today(), 1, 2);

        $this->travelTo(CarbonImmutable::parse('2026-08-03 10:30:00'));

        $this->artisan('spa:posalji-podsetnike')->assertSuccessful();

        Mail::assertNotQueued(RezervacijaPodsetnik::class);
    }
}
