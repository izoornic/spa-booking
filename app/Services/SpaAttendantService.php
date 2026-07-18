<?php

namespace App\Services;

use App\Enums\BookingState;
use App\Exceptions\BookingException;
use App\Models\SpaBooking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Attendant (domar) desk operations: look up a reservation from its QR/short code
 * and record what actually happened at the spa (arrival, headcount, no-show).
 */
class SpaAttendantService
{
    /**
     * Resolve a reservation from a scanned QR token or a typed short code.
     */
    public function findByCode(string $kod): SpaBooking
    {
        $booking = SpaBooking::poKodu($kod)->first();

        if ($booking === null) {
            throw BookingException::rezervacijaNijeNadjena();
        }

        return $booking;
    }

    /**
     * Confirm arrival and record the actual number of attendees.
     */
    public function confirm(SpaBooking $booking, int $evidentiranoOsoba): SpaBooking
    {
        return DB::transaction(function () use ($booking, $evidentiranoOsoba) {
            $this->assertMozeEvidentirati($booking);

            if ($evidentiranoOsoba < 0 || $evidentiranoOsoba > $booking->broj_osoba) {
                throw BookingException::nevalidnaEvidencija($booking->broj_osoba);
            }

            $booking->update([
                'stanje' => BookingState::Confirmed,
                'evidentirano_osoba' => $evidentiranoOsoba,
            ]);

            return $booking->fresh();
        });
    }

    /**
     * Mark the reservation as a no-show (nobody arrived).
     */
    public function noShow(SpaBooking $booking): SpaBooking
    {
        return DB::transaction(function () use ($booking) {
            $this->assertMozeEvidentirati($booking);

            $booking->update([
                'stanje' => BookingState::NoShow,
                'evidentirano_osoba' => 0,
                'je_trajna' => false,
            ]);

            return $booking->fresh();
        });
    }

    /**
     * A reservation can be evidenced only while still active (booked/confirmed)
     * and only on the calendar day of its termin.
     */
    private function assertMozeEvidentirati(SpaBooking $booking): void
    {
        if (! in_array($booking->stanje, [BookingState::Booked, BookingState::Confirmed], true)) {
            throw BookingException::nepotvrdiva();
        }

        if (! CarbonImmutable::instance($booking->datum)->isSameDay(CarbonImmutable::now())) {
            throw BookingException::neDanas();
        }
    }
}
