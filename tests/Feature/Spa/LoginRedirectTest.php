<?php

namespace Tests\Feature\Spa;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendant_lands_on_the_desk(): void
    {
        $attendant = User::factory()->attendant()->create();

        $this->post(route('login.store'), [
            'email' => $attendant->email,
            'password' => 'password',
        ])->assertRedirect(route('domar.home'));
    }

    public function test_manager_lands_on_the_dashboard(): void
    {
        $manager = User::factory()->manager()->create();

        $this->post(route('login.store'), [
            'email' => $manager->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');
    }
}
