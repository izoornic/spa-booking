<?php

namespace Database\Factories;

use App\Models\Zgrada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zgrada>
 */
class ZgradaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'naziv' => 'Zgrada '.fake()->unique()->numberBetween(1, 999),
            'adresa' => fake()->streetAddress(),
        ];
    }
}
