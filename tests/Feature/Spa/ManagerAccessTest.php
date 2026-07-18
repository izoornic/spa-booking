<?php

namespace Tests\Feature\Spa;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManagerAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function managerRoutes(): array
    {
        return [
            'rezervacije' => ['upravnik.rezervacije'],
            'blokade' => ['upravnik.blokade'],
            'konfiguracija' => ['upravnik.konfiguracija'],
        ];
    }

    #[DataProvider('managerRoutes')]
    public function test_guest_is_redirected(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    #[DataProvider('managerRoutes')]
    public function test_attendant_is_forbidden(string $route): void
    {
        $attendant = User::factory()->attendant()->create();

        $this->actingAs($attendant)->get(route($route))->assertForbidden();
    }

    public function test_manager_can_open_reservations(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('upravnik.rezervacije'))->assertOk();
    }
}
