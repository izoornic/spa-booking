<?php

namespace Tests\Feature\Spa;

use App\Enums\BookingState;
use App\Exceptions\BookingException;
use App\Services\SpaAttendantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class AttendantCheckinTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    public function test_new_reservation_gets_qr_token_and_short_code(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();

        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        $this->assertNotNull($booking->qr_token);
        $this->assertNotNull($booking->kratki_kod);
        $this->assertSame(6, strlen((string) $booking->kratki_kod));
    }

    public function test_attendant_confirms_arrival_and_records_attendance(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->today(), 1, 3);

        Livewire::test('pages::domar.rezervacija', ['kod' => $booking->kratki_kod])
            ->assertSet('prisutno', 3)
            ->set('prisutno', 2)
            ->call('potvrdi');

        $booking->refresh();
        $this->assertSame(BookingState::Confirmed, $booking->stanje);
        $this->assertSame(2, $booking->evidentirano_osoba);
    }

    public function test_attendant_marks_no_show(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->today(), 1, 3);

        Livewire::test('pages::domar.rezervacija', ['kod' => $booking->kratki_kod])
            ->call('nijeSePojavio');

        $booking->refresh();
        $this->assertSame(BookingState::NoShow, $booking->stanje);
        $this->assertSame(0, $booking->evidentirano_osoba);
    }

    public function test_reservation_cannot_be_evidenced_before_its_day(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        $this->expectException(BookingException::class);

        app(SpaAttendantService::class)->confirm($booking, 2);
    }

    public function test_detail_hides_actions_when_not_termin_day(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Livewire::test('pages::domar.rezervacija', ['kod' => $booking->kratki_kod])
            ->assertDontSee('Potvrdi dolazak')
            ->assertSee('samo na dan termina');
    }

    public function test_reservation_is_found_by_qr_token(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan(['broj' => '42']);
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Livewire::test('pages::domar.rezervacija', ['kod' => $booking->qr_token])
            ->assertSee('42');
    }

    public function test_unknown_code_shows_not_found(): void
    {
        $this->bootScenario();

        Livewire::test('pages::domar.rezervacija', ['kod' => 'NEMA00'])
            ->assertSet('booking', null)
            ->assertSee('nije pronađena');
    }

    public function test_desk_search_redirects_to_reservation(): void
    {
        $this->bootScenario();

        Livewire::test('pages::domar.home')
            ->set('kod', 'ABC123')
            ->call('pretrazi')
            ->assertRedirect(route('domar.rezervacija', ['kod' => 'ABC123']));
    }

    public function test_confirm_rejects_attendance_above_reserved(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->today(), 1, 2);

        $this->expectException(BookingException::class);

        app(SpaAttendantService::class)->confirm($booking, 5);
    }

    public function test_cancelled_reservation_cannot_be_evidenced(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);
        $this->service()->cancel($booking);

        $this->expectException(BookingException::class);

        app(SpaAttendantService::class)->confirm($booking->fresh(), 1);
    }

    public function test_desk_lists_upcoming_occupancy(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan(['broj' => '77']);
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Livewire::test('pages::domar.home')
            ->assertSee('77')
            ->assertSee('Evidencija dolaska');
    }
}
