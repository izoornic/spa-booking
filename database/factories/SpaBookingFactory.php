<?php

namespace Database\Factories;

use App\Enums\BookingState;
use App\Models\SpaBooking;
use App\Models\Stan;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpaBooking>
 */
class SpaBookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stan_id' => Stan::factory(),
            'zgrada_id' => fn (array $attributes) => Stan::whereKey($attributes['stan_id'])->value('zgrada_id'),
            'vlasnik_id' => null,
            'datum' => CarbonImmutable::tomorrow(),
            'slot_index' => 1,
            'broj_osoba' => 2,
            'stanje' => BookingState::Booked,
            'je_trajna' => false,
        ];
    }

    public function naDatum(DateTimeInterface $datum): static
    {
        return $this->state(fn (array $attributes) => ['datum' => $datum]);
    }

    public function uSlotu(int $slotIndex): static
    {
        return $this->state(fn (array $attributes) => ['slot_index' => $slotIndex]);
    }

    public function osoba(int $broj): static
    {
        return $this->state(fn (array $attributes) => ['broj_osoba' => $broj]);
    }

    public function trajna(): static
    {
        return $this->state(fn (array $attributes) => ['je_trajna' => true]);
    }

    public function stanje(BookingState $stanje): static
    {
        return $this->state(fn (array $attributes) => ['stanje' => $stanje]);
    }
}
