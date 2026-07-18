<?php

namespace App\Enums;

enum BookingState: string
{
    case Booked = 'booked';
    case Confirmed = 'confirmed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    case Moved = 'moved';

    /**
     * Human-readable label (Serbian).
     */
    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Rezervisano',
            self::Confirmed => 'Potvrđeno',
            self::NoShow => 'Nije se pojavio',
            self::Cancelled => 'Otkazano',
            self::Moved => 'Premešteno',
        };
    }

    /**
     * States that still occupy slot capacity.
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Booked, self::Confirmed];
    }
}
