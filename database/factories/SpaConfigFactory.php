<?php

namespace Database\Factories;

use App\Models\SpaConfig;
use App\Models\Zgrada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpaConfig>
 */
class SpaConfigFactory extends Factory
{
    /**
     * Define the model's default state (building defaults from the requirements).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zgrada_id' => Zgrada::factory(),
            'kapacitet' => 25,
            'max_rez_7d' => 4,
            'max_osoba' => 5,
            'horizont_dana' => 7,
            'radno_od' => '12:00',
            'radno_do' => '21:00',
            'broj_slotova' => 3,
            'min_sati_pre' => 2,
            'podsetnik_sati_pre' => 3,
            'zakljucaj_sati_pre' => 1,
            'blokiraj_dug' => false,
            'aktivan' => true,
        ];
    }
}
