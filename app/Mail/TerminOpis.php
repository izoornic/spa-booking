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

        return $booking->datum->format('d.m.Y.').' '.$config->slotOpis($booking->datum, $booking->slot_index);
    }
}
