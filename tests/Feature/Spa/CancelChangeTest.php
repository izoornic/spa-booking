<?php

namespace Tests\Feature\Spa;

use App\Enums\BookingState;
use App\Exceptions\BookingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class CancelChangeTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_cancel_frees_slot_and_promotes_next_to_permanent(): void
    {
        $this->bootScenario();
        $stan = $this->stan();
        $vlasnik = $this->vlasnikOf($stan);

        $prvi = $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 1, 2);
        $drugi = $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 2, 2);

        $this->assertTrue($prvi->fresh()->je_trajna);
        $this->assertFalse($drugi->fresh()->je_trajna);

        $this->service()->cancel($prvi);

        $this->assertSame(BookingState::Cancelled, $prvi->fresh()->stanje);
        $this->assertTrue($drugi->fresh()->je_trajna, 'Next reservation becomes permanent after cancel');
    }

    public function test_change_moves_reservation_to_new_slot(): void
    {
        $this->bootScenario();
        $stan = $this->stan();
        $vlasnik = $this->vlasnikOf($stan);

        $original = $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 1, 2);
        $izmenjen = $this->service()->change($original, $this->tomorrow(), 2, 4);

        $this->assertSame(BookingState::Cancelled, $original->fresh()->stanje);
        $this->assertSame(2, $izmenjen->slot_index);
        $this->assertSame(4, $izmenjen->broj_osoba);
    }

    public function test_failed_change_keeps_original_reservation(): void
    {
        $this->bootScenario();
        $stan = $this->stan();
        $vlasnik = $this->vlasnikOf($stan);

        $original = $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 1, 2);

        try {
            $this->service()->change($original, $this->tomorrow(), 2, 99); // invalid persons
            $this->fail('Expected BookingException.');
        } catch (BookingException) {
            // expected
        }

        $this->assertSame(BookingState::Booked, $original->fresh()->stanje);
    }
}
