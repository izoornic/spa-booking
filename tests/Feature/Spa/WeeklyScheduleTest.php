<?php

namespace Tests\Feature\Spa;

use App\Enums\BookingState;
use App\Exceptions\BookingException;
use App\Http\Middleware\EnsureOwner;
use App\Models\SpaBlokada;
use App\Models\SpaBooking;
use App\Models\SpaConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

/**
 * Each day of the week has its own termini. The scenario clock starts on
 * Monday 2026-08-03 08:00, so tomorrow() is a Tuesday.
 */
class WeeklyScheduleTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    private const array PODNE = ['start' => '12:00', 'end' => '15:00'];

    private const array POPODNE = ['start' => '15:00', 'end' => '18:00'];

    private const array VECE = ['start' => '18:00', 'end' => '21:00'];

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    /**
     * Default schedule with Tuesday (tomorrow) given its own termini.
     *
     * @param  list<array{start: string, end: string}>  $utorak
     */
    private function bootWithTuesday(array $utorak): void
    {
        $raspored = SpaConfig::podrazumevaniRaspored();
        $raspored[2] = $utorak;

        $this->bootScenario(['raspored' => $raspored]);
    }

    public function test_manager_can_save_different_termini_per_day(): void
    {
        $this->bootScenario();

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.konfiguracija')
            ->set('raspored.6', [
                ['start' => '10:00', 'end' => '12:00'],
                ['start' => '12:00', 'end' => '14:00'],
                ['start' => '16:00', 'end' => '20:00'],
                ['start' => '20:00', 'end' => '22:00'],
            ])
            ->set('raspored.7', [])
            ->call('sacuvaj')
            ->assertHasNoErrors();

        $config = $this->config->fresh();

        $this->assertCount(4, $config->slotsForWeekday(6));
        $this->assertSame(['start' => '20:00', 'end' => '22:00'], $config->slotsForWeekday(6)[4]);
        $this->assertSame([], $config->slotsForWeekday(7));
        $this->assertSame([1 => self::PODNE, 2 => self::POPODNE, 3 => self::VECE], $config->slotsForWeekday(1));
    }

    public function test_termini_are_saved_sorted_by_start(): void
    {
        $this->bootScenario();

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.konfiguracija')
            ->set('raspored.3', [self::VECE, self::PODNE])
            ->call('sacuvaj')
            ->assertHasNoErrors();

        $this->assertSame([self::PODNE, self::VECE], $this->config->fresh()->raspored[3]);
    }

    public function test_config_rejects_overlapping_termini(): void
    {
        $this->bootScenario();

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.konfiguracija')
            ->set('raspored.2', [self::PODNE, ['start' => '14:00', 'end' => '16:00']])
            ->call('sacuvaj')
            ->assertHasErrors('raspored.2.1.start');

        $this->assertCount(3, $this->config->fresh()->slotsForWeekday(2));
    }

    public function test_config_rejects_invalid_time_format(): void
    {
        $this->bootScenario();

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.konfiguracija')
            ->set('raspored.4.0.start', 'podne')
            ->call('sacuvaj')
            ->assertHasErrors('raspored.4.0.start');
    }

    public function test_add_remove_and_copy_termini_in_the_form(): void
    {
        $this->bootScenario();

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.konfiguracija')
            ->call('dodajTermin', 1)
            ->assertSet('raspored.1.3', ['start' => '21:00', 'end' => '23:59'])
            ->call('ukloniTermin', 1, 0)
            ->assertSet('raspored.1.0', self::POPODNE)
            ->call('kopirajNaSveDane', 1)
            ->assertSet('raspored.7', [self::POPODNE, self::VECE, ['start' => '21:00', 'end' => '23:59']])
            ->call('kopirajNaSveDane', 99)
            ->assertSet('raspored.7.0', self::POPODNE);
    }

    public function test_reservation_uses_the_termini_of_that_weekday(): void
    {
        Mail::fake();
        $this->bootWithTuesday([self::PODNE, self::POPODNE, self::VECE, ['start' => '21:00', 'end' => '23:00']]);
        $stan = $this->stan();

        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 4, 2);

        $this->assertSame(4, $booking->slot_index);
        $this->assertSame('21:00', $this->config->slotStartAt($booking->datum, 4)->format('H:i'));

        // Wednesday has only the default three termini.
        $this->expectException(BookingException::class);
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->inDays(2), 4, 2);
    }

    public function test_closed_day_cannot_be_booked_and_shows_as_closed(): void
    {
        $this->bootWithTuesday([]);
        $stan = $this->stan();

        try {
            $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);
            $this->fail('Booking a closed day should fail.');
        } catch (BookingException $e) {
            $this->assertSame(BookingException::nepostojeciSlot()->getMessage(), $e->getMessage());
        }

        session([EnsureOwner::SESSION_KEY => $this->vlasnikOf($stan)->id]);

        $component = Livewire::test('pages::spa.home')->assertSee('Zatvoreno — nema termina.');

        $utorak = collect($component->instance()->dani())->first(fn (array $dan): bool => $dan['datum']->isSameDay($this->tomorrow()));
        $this->assertSame([], $utorak['slotovi']);
    }

    public function test_owner_page_lists_the_weekly_schedule(): void
    {
        $this->bootWithTuesday([['start' => '16:00', 'end' => '19:00']]);
        $stan = $this->stan();
        session([EnsureOwner::SESSION_KEY => $this->vlasnikOf($stan)->id]);

        Livewire::test('pages::spa.home')
            ->assertSee('Utorak')
            ->assertSee('16:00–19:00')
            ->assertSee('Sreda – Nedelja');
    }

    public function test_inserting_an_earlier_termin_keeps_existing_reservations_on_their_time(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);
        $blokada = $this->service()->blokiraj($this->zgrada, $this->tomorrow(), 3, null, null);

        $this->service()->promeniRaspored($this->config, [
            ...SpaConfig::podrazumevaniRaspored(),
            2 => [['start' => '09:00', 'end' => '12:00'], self::PODNE, self::POPODNE, self::VECE],
        ]);

        $this->assertSame(2, $booking->fresh()->slot_index);
        $this->assertSame(4, $blokada->fresh()->slot_index);
        $this->assertSame('12:00–15:00', $this->config->fresh()->slotOpis($booking->datum, 2));
    }

    public function test_schedule_change_is_refused_when_it_drops_a_booked_termin(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 2, 2);

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.konfiguracija')
            ->set('kapacitet', 30)
            ->set('raspored.2', [self::PODNE, ['start' => '16:00', 'end' => '19:00']])
            ->call('sacuvaj')
            ->assertHasErrors('raspored')
            ->assertSee('04.08. 15:00–18:00');

        $config = $this->config->fresh();
        $this->assertSame(self::POPODNE, $config->slotsForWeekday(2)[2]);
        $this->assertSame(25, $config->kapacitet, 'Nothing is saved when the schedule is refused');
    }

    public function test_schedule_change_is_refused_when_it_drops_a_blocked_termin(): void
    {
        $this->bootScenario();
        $this->service()->blokiraj($this->zgrada, $this->tomorrow(), 3, 'Servis', null);

        $this->expectException(BookingException::class);
        $this->service()->promeniRaspored($this->config, [
            ...SpaConfig::podrazumevaniRaspored(),
            2 => [self::PODNE, self::POPODNE],
        ]);
    }

    public function test_cancelled_and_past_reservations_do_not_block_a_schedule_change(): void
    {
        $this->bootScenario(now: '2026-08-04 22:00:00');
        $stan = $this->stan();

        // Tuesday's evening termin already ended; tomorrow's (Wednesday) booking is cancelled.
        SpaBooking::factory()->for($this->zgrada)->for($stan)->for($this->vlasnikOf($stan))
            ->naDatum($this->today())->uSlotu(3)->osoba(2)->create();
        SpaBooking::factory()->for($this->zgrada)->for($stan)->for($this->vlasnikOf($stan))
            ->naDatum($this->tomorrow())->uSlotu(3)->osoba(2)->stanje(BookingState::Cancelled)->create();

        $raspored = SpaConfig::podrazumevaniRaspored();
        $raspored[2] = [self::PODNE];
        $raspored[3] = [self::PODNE];

        $this->service()->promeniRaspored($this->config, $raspored);

        $this->assertSame([1 => self::PODNE], $this->config->fresh()->slotsForWeekday(2));

        // The orphaned past booking still renders and does not count as upcoming.
        $this->assertSame('3. termin', $this->config->fresh()->slotOpis($this->today(), 3));
        $this->service()->refreshPermanent($stan);
    }

    public function test_blockade_options_follow_the_selected_date(): void
    {
        $this->bootWithTuesday([self::PODNE]);

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.blokade')
            ->set('datum', $this->inDays(2)->format('Y-m-d'))
            ->set('slot', 3)
            ->assertSet('slot', 3)
            ->set('datum', $this->tomorrow()->format('Y-m-d'))
            ->assertSet('slot', 0)
            ->assertDontSee('18:00–21:00');

        $this->expectException(BookingException::class);
        $this->service()->blokiraj($this->zgrada, $this->tomorrow(), 3, null, null);
    }

    public function test_dashboard_shows_closed_day(): void
    {
        $raspored = SpaConfig::podrazumevaniRaspored();
        $raspored[1] = [];
        $this->bootScenario(['raspored' => $raspored]);

        $this->actingAs($this->manager())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Zatvoreno — nema termina.');

        $this->assertSame(0, SpaBlokada::count());
    }
}
