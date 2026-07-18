<?php

namespace Tests\Feature\Spa;

use App\Enums\BookingState;
use App\Exceptions\BookingException;
use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class CapacityDisplacementTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function slotOccupancy(int $slot): int
    {
        return (int) SpaBooking::aktivne()
            ->uSlotu($this->zgrada->id, $this->tomorrow(), $slot)
            ->sum('broj_osoba');
    }

    public function test_apartment_without_permanent_displaces_a_conditional(): void
    {
        $this->bootScenario();
        $filler = $this->occupy($this->tomorrow(), 1, 25, trajna: false);

        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 3);

        $this->assertSame(BookingState::Booked, $booking->stanje);
        $this->assertSame(BookingState::Moved, $filler->fresh()->stanje);
        $this->assertSame(3, $this->slotOccupancy(1));
    }

    public function test_displaces_multiple_conditionals_when_capacity_requires(): void
    {
        $this->bootScenario(['kapacitet' => 6]);
        $f1 = $this->occupy($this->tomorrow(), 1, 2, trajna: false);
        $f2 = $this->occupy($this->tomorrow(), 1, 2, trajna: false);
        $f3 = $this->occupy($this->tomorrow(), 1, 2, trajna: false);

        $stan = $this->stan();
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 5);

        $displaced = collect([$f1, $f2, $f3])
            ->filter(fn (SpaBooking $b) => $b->fresh()->stanje === BookingState::Moved)
            ->count();

        $this->assertSame(3, $displaced);
        $this->assertSame(5, $this->slotOccupancy(1));
    }

    public function test_apartment_with_existing_reservation_cannot_displace(): void
    {
        $this->bootScenario(['kapacitet' => 5]);
        $this->occupy($this->tomorrow(), 2, 5, trajna: false);

        $stan = $this->stan();
        // This apartment already holds a permanent reservation.
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        $this->expectException(BookingException::class);
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 2, 2);
    }

    public function test_cannot_displace_permanent_bookings(): void
    {
        $this->bootScenario(['kapacitet' => 5]);
        $this->occupy($this->tomorrow(), 1, 5, trajna: true);

        $stan = $this->stan();

        $this->expectException(BookingException::class);
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 3);
    }
}
