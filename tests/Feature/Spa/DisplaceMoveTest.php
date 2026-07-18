<?php

namespace Tests\Feature\Spa;

use App\Enums\BookingState;
use App\Mail\RezervacijaOtkazana;
use App\Mail\RezervacijaPomerena;
use App\Models\SpaBooking;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class DisplaceMoveTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_displaced_booking_moves_to_first_free_slot(): void
    {
        $this->bootScenario();
        $filler = $this->occupy($this->tomorrow(), 1, 25, trajna: false);

        $stan = $this->stan();
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 3);

        $this->assertSame(BookingState::Moved, $filler->fresh()->stanje);

        $moved = SpaBooking::where('stan_id', $filler->stan_id)
            ->where('stanje', BookingState::Booked->value)
            ->first();

        $this->assertNotNull($moved);
        $this->assertFalse(
            $moved->datum->isSameDay($this->tomorrow()) && $moved->slot_index === 1,
            'Moved booking should not stay in the original slot'
        );
        Mail::assertQueued(RezervacijaPomerena::class);
    }

    public function test_displaced_booking_is_cancelled_when_no_free_slot(): void
    {
        $this->bootScenario(['broj_slotova' => 1, 'horizont_dana' => 1, 'kapacitet' => 5]);

        $today = CarbonImmutable::now()->startOfDay();
        $tomorrow = $this->tomorrow();

        // Apartment A: permanent today, conditional tomorrow (the only two possible slots).
        $stanA = $this->stan();
        SpaBooking::factory()->for($this->zgrada)->for($stanA)->for($this->vlasnikOf($stanA))
            ->naDatum($today)->uSlotu(1)->osoba(5)->state(['je_trajna' => true])->create();
        $aCond = SpaBooking::factory()->for($this->zgrada)->for($stanA)->for($this->vlasnikOf($stanA))
            ->naDatum($tomorrow)->uSlotu(1)->osoba(5)->state(['je_trajna' => false])->create();

        $stan = $this->stan();
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $tomorrow, 1, 5);

        $this->assertSame(BookingState::Cancelled, $aCond->fresh()->stanje);
        Mail::assertQueued(RezervacijaOtkazana::class);
    }
}
