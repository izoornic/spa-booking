<?php

namespace Tests\Feature\Spa;

use App\Enums\BookingState;
use App\Http\Middleware\EnsureOwner;
use App\Models\Stan;
use App\Models\Vlasnik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class BookingUiTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    private function ownerSession(Stan $stan): Vlasnik
    {
        $vlasnik = $this->vlasnikOf($stan);
        session([EnsureOwner::SESSION_KEY => $vlasnik->id]);

        return $vlasnik;
    }

    public function test_owner_can_reserve_a_slot_from_the_ui(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $this->ownerSession($stan);

        Livewire::test('pages::spa.home')
            ->call('openReserve', $this->tomorrow()->format('Y-m-d'), 1)
            ->assertSet('showReserve', true)
            ->set('reserveOsoba', 3)
            ->call('reserve')
            ->assertSet('showReserve', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('spa_booking', [
            'stan_id' => $stan->id,
            'slot_index' => 1,
            'broj_osoba' => 3,
            'stanje' => BookingState::Booked->value,
        ]);
    }

    public function test_owner_can_cancel_their_reservation(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $this->ownerSession($stan);

        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Livewire::test('pages::spa.home')
            ->call('cancel', $booking->id);

        $this->assertSame(BookingState::Cancelled, $booking->fresh()->stanje);
    }

    public function test_owner_can_change_slot(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $this->ownerSession($stan);

        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Livewire::test('pages::spa.home')
            ->call('openChange', $booking->id)
            ->assertSet('changingId', $booking->id)
            ->set('reserveSlot', 2)
            ->call('reserve')
            ->assertHasNoErrors();

        $this->assertSame(BookingState::Cancelled, $booking->fresh()->stanje);
        $this->assertDatabaseHas('spa_booking', [
            'stan_id' => $stan->id,
            'slot_index' => 2,
            'stanje' => BookingState::Booked->value,
        ]);
    }

    public function test_my_reservations_show_permanent_badge(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $this->ownerSession($stan);

        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Livewire::test('pages::spa.home')
            ->assertSee('Trajna')
            ->assertSee('Moje rezervacije');
    }

    public function test_full_slot_without_displacement_right_is_not_reservable(): void
    {
        Mail::fake();
        $this->bootScenario(['kapacitet' => 5]);
        $stan = $this->stan();
        $this->ownerSession($stan);

        // Owner already holds a permanent reservation → cannot displace.
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);
        // Another apartment fills slot 2 to capacity.
        $this->occupy($this->tomorrow(), 2, 5);

        Livewire::test('pages::spa.home')
            ->call('openReserve', $this->tomorrow()->format('Y-m-d'), 2)
            ->call('reserve')
            ->assertSet('showReserve', true);

        $this->assertDatabaseMissing('spa_booking', [
            'stan_id' => $stan->id,
            'slot_index' => 2,
        ]);
    }
}
