<?php

namespace Tests\Feature\Spa;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_route_is_not_registered(): void
    {
        $this->assertFalse(app('router')->has('register'));
    }

    public function test_registration_page_returns_404(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_login_page_still_works(): void
    {
        $this->get(route('login'))->assertOk();
    }
}
