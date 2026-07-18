<?php

namespace App\Mail;

use App\Models\SpaBooking;

class TerminOpis
{
    /**
     * Human-readable "date + slot window" label for a reservation.
     */
    public static function za(SpaBooking $booking): string
    {
        $config = $booking->zgrada->config;
        $window = $config->slotWindow($booking->slot_index);

        return $booking->datum->format('d.m.Y.').' '.$window['start'].'–'.$window['end'];
    }
}
