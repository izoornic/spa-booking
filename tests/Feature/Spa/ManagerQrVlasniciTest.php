<?php

namespace Tests\Feature\Spa;

use App\Models\Stan;
use App\Models\User;
use App\Models\Vlasnik;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class ManagerQrVlasniciTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_manager_sees_owner_name_and_access_link(): void
    {
        $this->bootScenario();
        $stan = $this->stan(['broj' => '5']);
        $vlasnik = $this->vlasnikOf($stan);

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.qr-vlasnici')
            ->assertOk()
            ->assertSee($vlasnik->punoIme())
            ->assertSee('Stan 5')
            ->assertSee($vlasnik->token); // per-owner access token encoded in the QR URL
    }

    public function test_inactive_owners_are_not_listed(): void
    {
        $this->bootScenario();
        $this->stan(['broj' => '1']);

        $inactiveStan = Stan::factory()
            ->for($this->zgrada)
            ->has(Vlasnik::factory()->state(['aktivan' => false]), 'vlasnici')
            ->create(['broj' => '2']);
        $inactive = $inactiveStan->vlasnici()->firstOrFail();

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.qr-vlasnici')
            ->assertOk()
            ->assertDontSee($inactive->token);
    }

    public function test_manager_can_issue_a_new_qr_token_for_an_owner(): void
    {
        $this->bootScenario();
        $vlasnik = $this->vlasnikOf($this->stan(['broj' => '5']));
        $stari = $vlasnik->token;

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.qr-vlasnici')
            ->call('regenerisi', $vlasnik->id)
            ->assertOk()
            ->assertDontSee($stari)
            ->assertSee($vlasnik->fresh()->token);

        $this->assertNotSame($stari, $vlasnik->fresh()->token);
    }

    public function test_manager_cannot_regenerate_a_token_for_another_building(): void
    {
        $this->bootScenario();
        $this->stan(['broj' => '1']);

        $otherStan = Stan::factory()
            ->has(Vlasnik::factory(), 'vlasnici')
            ->create();
        $other = $otherStan->vlasnici()->firstOrFail();
        $stari = $other->token;

        try {
            Livewire::actingAs($this->manager())
                ->test('pages::upravnik.qr-vlasnici')
                ->call('regenerisi', $other->id);

            $this->fail('Očekivan ModelNotFoundException za vlasnika iz druge zgrade.');
        } catch (ModelNotFoundException) {
            // Vlasnik iz druge zgrade nije u opsegu ove stranice.
        }

        $this->assertSame($stari, $other->fresh()->token);
    }

    public function test_owners_of_other_buildings_are_not_listed(): void
    {
        $this->bootScenario();
        $mine = $this->vlasnikOf($this->stan(['broj' => '1']));

        // A second building with its own owner.
        $otherStan = Stan::factory()
            ->has(Vlasnik::factory(), 'vlasnici')
            ->create();
        $other = $otherStan->vlasnici()->firstOrFail();

        Livewire::actingAs($this->manager())
            ->test('pages::upravnik.qr-vlasnici')
            ->assertOk()
            ->assertSee($mine->token)
            ->assertDontSee($other->token);
    }
}
