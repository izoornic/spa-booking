<?php

namespace Tests\Feature\Spa;

use App\Enums\BookingState;
use App\Exceptions\BookingException;
use App\Models\SpaBooking;
use App\Models\Stan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

/**
 * Once a slot enters its locked window (zakljucaj_sati_pre hours before it starts),
 * every reservation in it is guaranteed (trajna) and can no longer be displaced.
 */
class SlotLockTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * Create an active conditional booking for a specific apartment.
     */
    private function bookingFor(Stan $stan, CarbonImmutable $datum, int $slot): SpaBooking
    {
        return SpaBooking::factory()
            ->for($this->zgrada)
            ->for($stan)
            ->for($this->vlasnikOf($stan))
            ->naDatum($datum)
            ->uSlotu($slot)
            ->create();
    }

    public function test_conditional_cannot_be_displaced_once_slot_is_locked(): void
    {
        // Slot 1 starts 12:00; lock window opens 11:00. min_sati_pre=0 so booking is
        // otherwise still allowed at 11:30 — only the lock must stop the displacement.
        $this->bootScenario(['min_sati_pre' => 0], '2026-08-03 11:30:00');
        $filler = $this->occupy($this->today(), 1, 25, trajna: false);

        $stan = $this->stan();

        try {
            $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->today(), 1, 3);
            $this->fail('Reservation into a locked, full slot should have been rejected.');
        } catch (BookingException) {
            // expected
        }

        $this->assertSame(BookingState::Booked, $filler->fresh()->stanje, 'Locked conditional was not displaced');
    }

    public function test_conditional_is_still_displaced_before_the_lock_window(): void
    {
        // Same setup but 10:30 — before the 11:00 lock — so displacement still works.
        $this->bootScenario(['min_sati_pre' => 0], '2026-08-03 10:30:00');
        $filler = $this->occupy($this->today(), 1, 25, trajna: false);

        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->today(), 1, 3);

        $this->assertSame(BookingState::Booked, $booking->stanje);
        $this->assertSame(BookingState::Moved, $filler->fresh()->stanje);
    }

    public function test_refresh_marks_every_booking_in_a_locked_slot_as_permanent(): void
    {
        // 17:30: slot 2 (15:00-18:00) is ongoing/nearest, slot 3 (18:00-21:00) locks at 17:00.
        $this->bootScenario([], '2026-08-03 17:30:00');
        $stan = $this->stan();

        $slot2 = $this->bookingFor($stan, $this->today(), 2);
        $slot3 = $this->bookingFor($stan, $this->today(), 3);

        $this->service()->refreshPermanent($stan);

        $this->assertTrue($slot2->fresh()->je_trajna, 'Nearest upcoming reservation is permanent');
        $this->assertTrue($slot3->fresh()->je_trajna, 'Locked slot makes the non-nearest reservation permanent too');
    }
}
