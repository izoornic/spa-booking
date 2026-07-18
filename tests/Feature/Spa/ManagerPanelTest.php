<?php

namespace Tests\Feature\Spa;

use App\Enums\BookingState;
use App\Exceptions\BookingException;
use App\Mail\SlotBlokiranObavestenje;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class ManagerPanelTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_manager_can_cancel_any_reservation(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.rezervacije')
            ->call('otkazi', $booking->id);

        $this->assertSame(BookingState::Cancelled, $booking->fresh()->stanje);
    }

    public function test_manager_cancel_bypasses_the_tenant_cutoff(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->today(), 1, 2);

        // Past the tenant cut-off (slot 1 starts 12:00, min_sati_pre 2h).
        $this->travelTo(CarbonImmutable::parse('2026-08-03 11:30:00'));

        // Tenant path is now blocked...
        try {
            $this->service()->cancel($booking);
            $this->fail('Tenant cancel should be blocked past the cut-off.');
        } catch (BookingException) {
            // expected
        }

        // ...but the manager can still cancel.
        $this->service()->cancelAsManager($booking->fresh());

        $this->assertSame(BookingState::Cancelled, $booking->fresh()->stanje);
    }

    public function test_blocking_a_slot_cancels_and_emails_affected_owners(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.blokade')
            ->set('datum', $this->tomorrow()->format('Y-m-d'))
            ->set('slot', 1)
            ->set('razlog', 'Održavanje')
            ->call('kreiraj')
            ->assertHasNoErrors();

        $this->assertSame(BookingState::Cancelled, $booking->fresh()->stanje);
        $this->assertDatabaseHas('spa_blokada', [
            'zgrada_id' => $this->zgrada->id,
            'slot_index' => 1,
            'razlog' => 'Održavanje',
        ]);
        Mail::assertQueued(SlotBlokiranObavestenje::class);
    }

    public function test_blocked_slot_is_not_reservable(): void
    {
        Mail::fake();
        $this->bootScenario();

        $this->service()->blokiraj($this->zgrada, $this->tomorrow(), 1, null, $this->manager());

        $stan = $this->stan();

        $this->expectException(BookingException::class);
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);
    }

    public function test_removing_a_blockade_reopens_the_slot(): void
    {
        Mail::fake();
        $this->bootScenario();
        $blokada = $this->service()->blokiraj($this->zgrada, $this->tomorrow(), 1, null, $this->manager());

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.blokade')
            ->call('obrisi', $blokada->id);

        $this->assertDatabaseMissing('spa_blokada', ['id' => $blokada->id]);

        // Slot is bookable again.
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);
        $this->assertSame(BookingState::Booked, $booking->stanje);
    }

    public function test_manager_can_update_config(): void
    {
        $this->bootScenario();

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.konfiguracija')
            ->set('kapacitet', 30)
            ->set('max_osoba', 6)
            ->call('sacuvaj')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('spa_config', [
            'zgrada_id' => $this->zgrada->id,
            'kapacitet' => 30,
            'max_osoba' => 6,
        ]);
    }

    public function test_config_rejects_end_before_start(): void
    {
        $this->bootScenario();

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.konfiguracija')
            ->set('radno_od', '18:00')
            ->set('radno_do', '15:00')
            ->call('sacuvaj')
            ->assertHasErrors('radno_do');
    }
}
