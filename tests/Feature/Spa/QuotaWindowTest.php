<?php

namespace Tests\Feature\Spa;

use App\Exceptions\BookingException;
use App\Models\SpaBlokada;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class QuotaWindowTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_rejects_date_beyond_horizon(): void
    {
        $this->bootScenario();
        $stan = $this->stan();

        $this->expectException(BookingException::class);
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->inDays(8), 1, 2);
    }

    public function test_rejects_booking_too_close_to_slot_start(): void
    {
        $this->bootScenario(now: '2026-08-03 11:00:00'); // slot 1 starts 12:00 → only 1h away

        $stan = $this->stan();

        $this->expectException(BookingException::class);
        $this->service()->reserve($stan, $this->vlasnikOf($stan), CarbonImmutable::now()->startOfDay(), 1, 2);
    }

    public function test_rejects_persons_above_max(): void
    {
        $this->bootScenario();
        $stan = $this->stan();

        $this->expectException(BookingException::class);
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 6);
    }

    public function test_rejects_duplicate_slot_for_same_apartment(): void
    {
        $this->bootScenario();
        $stan = $this->stan();
        $vlasnik = $this->vlasnikOf($stan);

        $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 1, 2);

        $this->expectException(BookingException::class);
        $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 1, 2);
    }

    public function test_rejects_when_apartment_has_debt_and_debt_blocking_on(): void
    {
        $this->bootScenario(['blokiraj_dug' => true]);
        $stan = $this->stan(['ima_dug' => true]);

        $this->expectException(BookingException::class);
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);
    }

    public function test_rejects_blocked_whole_day(): void
    {
        $this->bootScenario();
        $stan = $this->stan();

        SpaBlokada::create([
            'zgrada_id' => $this->zgrada->id,
            'datum' => $this->tomorrow(),
            'slot_index' => null,
            'razlog' => 'Održavanje',
        ]);

        $this->expectException(BookingException::class);
        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 2, 2);
    }

    public function test_blocked_single_slot_only_affects_that_slot(): void
    {
        $this->bootScenario();
        $stan = $this->stan();

        SpaBlokada::create([
            'zgrada_id' => $this->zgrada->id,
            'datum' => $this->tomorrow(),
            'slot_index' => 1,
        ]);

        // Slot 2 is still bookable.
        $booking = $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 2, 2);
        $this->assertNotNull($booking->id);
    }

    public function test_rejects_when_quota_reached(): void
    {
        $this->bootScenario(['max_rez_7d' => 2]);
        $stan = $this->stan();
        $vlasnik = $this->vlasnikOf($stan);

        $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 1, 2);
        $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 2, 2);

        $this->expectException(BookingException::class);
        $this->service()->reserve($stan, $vlasnik, $this->tomorrow(), 3, 2);
    }
}
