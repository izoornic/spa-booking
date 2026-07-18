<?php

namespace App\Console\Commands;

use App\Mail\RezervacijaPodsetnik;
use App\Models\SpaBooking;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('spa:posalji-podsetnike')]
#[Description('Send a reminder email for each active reservation whose slot starts within its building\'s reminder window.')]
class PosaljiPodsetnike extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = CarbonImmutable::now();

        $bookings = SpaBooking::aktivne()
            ->whereNull('podsetnik_poslat_at')
            ->whereDate('datum', '>=', $now->startOfDay())
            ->whereDate('datum', '<=', $now->startOfDay()->addDay())
            ->with(['zgrada.config', 'vlasnik'])
            ->get();

        $sent = 0;

        foreach ($bookings as $booking) {
            $config = $booking->zgrada?->config;

            if ($config === null || $config->podsetnik_sati_pre <= 0) {
                continue;
            }

            $start = $config->slotStartAt($booking->datum, $booking->slot_index);

            // Only reservations whose slot is still upcoming and within the reminder window.
            if ($start->lte($now) || $start->gt($now->addHours($config->podsetnik_sati_pre))) {
                continue;
            }

            $booking->update(['podsetnik_poslat_at' => $now]);

            if ($booking->vlasnik?->email) {
                Mail::to($booking->vlasnik->email)->queue(new RezervacijaPodsetnik($booking));
                $sent++;
            }
        }

        $this->info("Poslato podsetnika: {$sent}.");

        return self::SUCCESS;
    }
}
