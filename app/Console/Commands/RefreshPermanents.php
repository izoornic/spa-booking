<?php

namespace App\Console\Commands;

use App\Models\SpaBooking;
use App\Models\Stan;
use App\Services\SpaBookingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('spa:refresh-permanents')]
#[Description('Re-designate each apartment\'s nearest upcoming reservation as permanent (trajna).')]
class RefreshPermanents extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SpaBookingService $service): int
    {
        $stanIds = SpaBooking::aktivne()->distinct()->pluck('stan_id');

        Stan::whereIn('id', $stanIds)->each(function (Stan $stan) use ($service): void {
            $service->refreshPermanent($stan);
        });

        $this->info("Osveženo trajnih rezervacija za {$stanIds->count()} stan(ova).");

        return self::SUCCESS;
    }
}
