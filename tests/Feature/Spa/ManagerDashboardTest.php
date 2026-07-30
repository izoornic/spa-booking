<?php

namespace Tests\Feature\Spa;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class ManagerDashboardTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_manager_sees_todays_occupancy_and_shortcuts(): void
    {
        $this->bootScenario();
        $this->occupy($this->today(), 2, 3);

        Livewire::actingAs($this->manager())
            ->test('pages::dashboard')
            ->assertOk()
            ->assertSee('Rezervacija danas')
            ->assertSee('15:00–18:00')
            ->assertSee('3/25')
            ->assertSee('QR kodovi za vlasnike');
    }

    public function test_blocked_slot_is_marked_instead_of_showing_free_capacity(): void
    {
        $this->bootScenario();
        $this->service()->blokiraj($this->zgrada, $this->today(), 1, 'Održavanje', null);

        Livewire::actingAs($this->manager())
            ->test('pages::dashboard')
            ->assertOk()
            ->assertSee('Blokiran');
    }

    public function test_attendant_is_pointed_to_their_own_page_instead(): void
    {
        $this->bootScenario();
        $this->occupy($this->today(), 1, 2);

        Livewire::actingAs(User::factory()->attendant()->create())
            ->test('pages::dashboard')
            ->assertOk()
            ->assertDontSee('Rezervacija danas')
            ->assertSee('Otvori pregled domara');
    }

    public function test_user_without_a_role_sees_no_spa_content(): void
    {
        $this->bootScenario();
        $this->occupy($this->today(), 1, 2);

        Livewire::actingAs(User::factory()->state(['role' => null])->create())
            ->test('pages::dashboard')
            ->assertOk()
            ->assertDontSee('Rezervacija danas')
            ->assertSee('Nemate dodeljenu ulogu');
    }
}
