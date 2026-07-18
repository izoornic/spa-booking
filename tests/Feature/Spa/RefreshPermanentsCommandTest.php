<?php

namespace Tests\Feature\Spa;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class RefreshPermanentsCommandTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    public function test_next_reservation_becomes_permanent_after_slot_passes(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $vlasnik = $this->vlasnikOf($stan);

        $prvi = $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 1, 2);
        $drugi = $this->service()->reserve($stan, $vlasnik, $this->inDays(2), 1, 2);

        $this->assertTrue($prvi->fresh()->je_trajna);
        $this->assertFalse($drugi->fresh()->je_trajna);

        // Move time past the first reservation's slot (default slot 1 ends 15:00).
        $this->travelTo(CarbonImmutable::parse('2026-08-04 16:00:00'));

        $this->artisan('spa:refresh-permanents')->assertSuccessful();

        $this->assertFalse($prvi->fresh()->je_trajna, 'Passed reservation is no longer permanent');
        $this->assertTrue($drugi->fresh()->je_trajna, 'Next upcoming reservation becomes permanent');
    }
}
