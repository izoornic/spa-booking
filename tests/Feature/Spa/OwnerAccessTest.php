<?php

namespace Tests\Feature\Spa;

use App\Http\Middleware\EnsureOwner;
use App\Models\Vlasnik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_starts_owner_session_and_redirects(): void
    {
        $vlasnik = Vlasnik::factory()->create();

        $response = $this->get(route('spa.access', $vlasnik->token));

        $response->assertRedirect(route('spa.home'));
        $this->assertEquals($vlasnik->id, session(EnsureOwner::SESSION_KEY));
    }

    public function test_owner_can_view_home_after_access(): void
    {
        $vlasnik = Vlasnik::factory()->create(['ime' => 'Petar']);

        $this->get(route('spa.access', $vlasnik->token));

        $this->get(route('spa.home'))
            ->assertOk()
            ->assertSee('Petar');
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->get(route('spa.access', 'ne-postoji'))->assertNotFound();
    }

    public function test_inactive_owner_token_returns_404(): void
    {
        $vlasnik = Vlasnik::factory()->neaktivan()->create();

        $this->get(route('spa.access', $vlasnik->token))->assertNotFound();
    }

    public function test_home_requires_owner_session(): void
    {
        $this->get(route('spa.home'))->assertForbidden();
    }
}
