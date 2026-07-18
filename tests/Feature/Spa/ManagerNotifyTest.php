<?php

namespace Tests\Feature\Spa;

use App\Mail\RezervacijaObavestenjeUpravniku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class ManagerNotifyTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    public function test_manager_is_notified_on_new_reservation(): void
    {
        Mail::fake();
        $this->bootScenario();
        User::factory()->manager()->create(['email' => 'upravnik@spa.test']);
        $stan = $this->stan();

        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Mail::assertQueued(
            RezervacijaObavestenjeUpravniku::class,
            fn (RezervacijaObavestenjeUpravniku $mail): bool => $mail->hasTo('upravnik@spa.test'),
        );
    }

    public function test_attendant_is_not_notified(): void
    {
        Mail::fake();
        $this->bootScenario();
        User::factory()->attendant()->create(['email' => 'domar@spa.test']);
        $stan = $this->stan();

        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Mail::assertNotQueued(
            RezervacijaObavestenjeUpravniku::class,
            fn (RezervacijaObavestenjeUpravniku $mail): bool => $mail->hasTo('domar@spa.test'),
        );
    }

    public function test_no_manager_notification_when_none_exist(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();

        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Mail::assertNotQueued(RezervacijaObavestenjeUpravniku::class);
    }
}
