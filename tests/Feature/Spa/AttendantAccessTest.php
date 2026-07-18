<?php

namespace Tests\Feature\Spa;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('domar.home'))->assertRedirect(route('login'));
    }

    public function test_attendant_can_open_the_desk(): void
    {
        $attendant = User::factory()->attendant()->create();

        $this->actingAs($attendant)->get(route('domar.home'))->assertOk();
    }

    public function test_manager_can_also_open_the_desk(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('domar.home'))->assertOk();
    }

    public function test_user_without_staff_role_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => null]);

        $this->actingAs($user)->get(route('domar.home'))->assertForbidden();
    }
}
