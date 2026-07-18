<?php

namespace Database\Seeders;

use App\Models\SpaConfig;
use App\Models\Stan;
use App\Models\User;
use App\Models\Vlasnik;
use App\Models\Zgrada;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Staff accounts (password login).
        User::factory()->manager()->create([
            'name' => 'Upravnik',
            'email' => 'manager@spa.test',
        ]);

        User::factory()->attendant()->create([
            'name' => 'Domar',
            'email' => 'attendant@spa.test',
        ]);

        // One building with its spa config.
        $zgrada = Zgrada::factory()->create([
            'naziv' => 'Spa Rezidencija',
            'adresa' => 'Bulevar 1, Beograd',
        ]);

        SpaConfig::factory()->for($zgrada)->create();

        // ~20 apartments, each with one owner (token auto-generated).
        Stan::factory()
            ->count(20)
            ->for($zgrada)
            ->has(Vlasnik::factory(), 'vlasnici')
            ->create();

        // Print a few owner access links for manual testing.
        $baseUrl = rtrim(config('app.url'), '/');

        $this->command->info('Owner pristupni linkovi (primeri):');

        Vlasnik::query()->with('stan')->take(3)->get()->each(function (Vlasnik $vlasnik) use ($baseUrl) {
            $this->command->line("  Stan {$vlasnik->stan->broj} ({$vlasnik->punoIme()}): {$baseUrl}/pristup/{$vlasnik->token}");
        });
    }
}
