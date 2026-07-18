<?php

namespace Tests\Concerns;

use App\Enums\BookingState;
use App\Models\SpaBooking;
use App\Models\SpaConfig;
use App\Models\Stan;
use App\Models\Vlasnik;
use App\Models\Zgrada;
use App\Services\SpaBookingService;
use Carbon\CarbonImmutable;

trait SpaScenario
{
    protected Zgrada $zgrada;

    protected SpaConfig $config;

    /**
     * Freeze time and create a building with spa config.
     *
     * @param  array<string, mixed>  $configOverrides
     */
    protected function bootScenario(array $configOverrides = [], string $now = '2026-08-03 08:00:00'): void
    {
        $this->travelTo(CarbonImmutable::parse($now));

        $this->zgrada = Zgrada::factory()->create();
        $this->config = SpaConfig::factory()->for($this->zgrada)->create($configOverrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function stan(array $overrides = []): Stan
    {
        return Stan::factory()
            ->for($this->zgrada)
            ->has(Vlasnik::factory(), 'vlasnici')
            ->create($overrides);
    }

    protected function vlasnikOf(Stan $stan): Vlasnik
    {
        return $stan->vlasnici()->firstOrFail();
    }

    protected function service(): SpaBookingService
    {
        return app(SpaBookingService::class);
    }

    protected function tomorrow(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDay()->startOfDay();
    }

    protected function inDays(int $days): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays($days)->startOfDay();
    }

    /**
     * Occupy a slot with a booking from a fresh apartment (for capacity setups).
     */
    protected function occupy(CarbonImmutable $datum, int $slot, int $persons, bool $trajna = false): SpaBooking
    {
        $stan = $this->stan();

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
}
