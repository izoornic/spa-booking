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
            'raspored' => SpaConfig::podrazumevaniRaspored(),
            'min_sati_pre' => 2,
            'podsetnik_sati_pre' => 3,
            'zakljucaj_sati_pre' => 1,
            'blokiraj_dug' => false,
            'aktivan' => true,
        ];
    }
}
