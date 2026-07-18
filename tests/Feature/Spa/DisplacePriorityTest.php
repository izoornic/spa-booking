<?php

namespace Tests\Feature\Spa;

use App\Enums\BookingState;
use App\Models\SpaBooking;
use App\Models\Stan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class DisplacePriorityTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    private function booking(Stan $stan, CarbonImmutable $datum, int $slot, int $persons, bool $trajna): SpaBooking
    {
        return SpaBooking::factory()
            ->for($this->zgrada)
            ->for($stan)
            ->for($this->vlasnikOf($stan))
            ->naDatum($datum)
            ->uSlotu($slot)
            ->osoba($persons)
            ->stanje(BookingState::Booked)
            ->state(['je_trajna' => $trajna])
            ->create();
    }

    public function test_conditional_with_nearest_preceding_reservation_is_displaced_first(): void
    {
        Mail::fake();
        $this->bootScenario(['kapacitet' => 4]);

        $today = CarbonImmutable::now()->startOfDay();
        $tomorrow = $this->tomorrow();

        // Apartment A: permanent at tomorrow slot 2 (near the target slot 3).
        $stanA = $this->stan();
        $this->booking($stanA, $tomorrow, 2, 2, trajna: true);
        $aCond = $this->booking($stanA, $tomorrow, 3, 2, trajna: false);

        // Apartment B: permanent at today slot 1 (far from the target slot 3).
        $stanB = $this->stan();
        $this->booking($stanB, $today, 1, 2, trajna: true);
        $bCond = $this->booking($stanB, $tomorrow, 3, 2, trajna: false);

        // A new apartment books the full target slot; only enough to displace one.
        $stan = $this->stan();
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $tomorrow, 3, 2);

        $this->assertSame(BookingState::Moved, $aCond->fresh()->stanje, 'A (nearest preceding) should be displaced');
        $this->assertSame(BookingState::Booked, $bCond->fresh()->stanje, 'B (far preceding) should keep its slot');
    }
}
