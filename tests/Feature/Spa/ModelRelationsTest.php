<?php

namespace Tests\Feature\Spa;

use App\Enums\StaffRole;
use App\Models\SpaConfig;
use App\Models\Stan;
use App\Models\User;
use App\Models\Vlasnik;
use App\Models\Zgrada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_building_apartment_owner_config_chain(): void
    {
        $zgrada = Zgrada::factory()->create();
        SpaConfig::factory()->for($zgrada)->create();
        $stan = Stan::factory()->for($zgrada)->create();
        $vlasnik = Vlasnik::factory()->for($stan)->create();

        $this->assertTrue($zgrada->config->is(SpaConfig::first()));
        $this->assertTrue($zgrada->stanovi->first()->is($stan));
        $this->assertTrue($vlasnik->stan->is($stan));
        $this->assertTrue($stan->zgrada->is($zgrada));
        $this->assertTrue($stan->vlasnici->first()->is($vlasnik));
    }

    public function test_owner_token_is_generated_on_create(): void
    {
        $vlasnik = Vlasnik::factory()->create();

        $this->assertNotEmpty($vlasnik->token);
        $this->assertSame(40, strlen($vlasnik->token));
    }

    public function test_user_role_helpers(): void
    {
        $manager = User::factory()->manager()->create();
        $attendant = User::factory()->attendant()->create();

        $this->assertTrue($manager->isManager());
        $this->assertFalse($manager->isAttendant());
        $this->assertSame(StaffRole::Manager, $manager->role);

        $this->assertTrue($attendant->isAttendant());
        $this->assertFalse($attendant->isManager());
    }
}
