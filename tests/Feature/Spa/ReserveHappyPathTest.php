<?php

namespace Tests\Feature\Spa;

use App\Enums\BookingState;
use App\Mail\RezervacijaPotvrda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class ReserveHappyPathTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    public function test_reserving_an_empty_slot_creates_a_permanent_booking(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();

        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 3);

        $this->assertSame(BookingState::Booked, $booking->stanje);
        $this->assertTrue($booking->je_trajna);
        $this->assertSame(3, $booking->broj_osoba);
        $this->assertSame($stan->id, $booking->stan_id);

        Mail::assertQueued(RezervacijaPotvrda::class);
    }

    public function test_second_booking_is_conditional_not_permanent(): void
    {
        $this->bootScenario();
        $stan = $this->stan();
        $vlasnik = $this->vlasnikOf($stan);

        $prvi = $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 1, 2);
        $drugi = $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 2, 2);

        $this->assertTrue($prvi->fresh()->je_trajna);
        $this->assertFalse($drugi->fresh()->je_trajna);
    }
}
