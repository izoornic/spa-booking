<?php

namespace Database\Factories;

use App\Models\Stan;
use App\Models\Zgrada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stan>
 */
class StanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zgrada_id' => Zgrada::factory(),
            'broj' => (string) fake()->unique()->numberBetween(1, 500),
            'sprat' => (string) fake()->numberBetween(0, 10),
            'ima_dug' => false,
        ];
    }

    /**
     * Apartment flagged as having outstanding debt.
     */
    public function saDugom(): static
    {
        return $this->state(fn (array $attributes) => ['ima_dug' => true]);
    }
}
