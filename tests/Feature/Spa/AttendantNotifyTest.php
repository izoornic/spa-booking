<?php

namespace Tests\Feature\Spa;

use App\Mail\RezervacijaObavestenjeDomaru;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SpaScenario;
use Tests\TestCase;

class AttendantNotifyTest extends TestCase
{
    use RefreshDatabase, SpaScenario;

    public function test_attendant_is_notified_on_new_reservation(): void
    {
        Mail::fake();
        $this->bootScenario();
        User::factory()->attendant()->create(['email' => 'domar@spa.test']);
        $stan = $this->stan();

        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Mail::assertQueued(
            RezervacijaObavestenjeDomaru::class,
            fn (RezervacijaObavestenjeDomaru $mail): bool => $mail->hasTo('domar@spa.test'),
        );
    }

    public function test_manager_is_not_notified(): void
    {
        Mail::fake();
        $this->bootScenario();
        User::factory()->manager()->create(['email' => 'upravnik@spa.test']);
        $stan = $this->stan();

        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Mail::assertNotQueued(
            RezervacijaObavestenjeDomaru::class,
            fn (RezervacijaObavestenjeDomaru $mail): bool => $mail->hasTo('upravnik@spa.test'),
        );
    }

    public function test_no_attendant_notification_when_none_exist(): void
    {
        Mail::fake();
        $this->bootScenario();
        $stan = $this->stan();

        $this->service()->reserve($stan, $this->vlasnikOf($stan), $this->tomorrow(), 1, 2);

        Mail::assertNotQueued(RezervacijaObavestenjeDomaru::class);
    }
}
