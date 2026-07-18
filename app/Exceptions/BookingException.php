<?php

namespace App\Exceptions;

use RuntimeException;

class BookingException extends RuntimeException
{
    public static function konfiguracijaNeaktivna(): self
    {
        return new self('Rezervacije za ovu zgradu trenutno nisu aktivne.');
    }

    public static function nepostojeciSlot(): self
    {
        return new self('Izabrani termin ne postoji.');
    }

    public static function vanHorizonta(int $dana): self
    {
        return new self("Rezervacije su moguće najviše {$dana} dana unapred.");
    }

    public static function prekasno(int $sati): self
    {
        return new self("Termin se mora rezervisati najmanje {$sati}h pre početka.");
    }

    public static function nevalidanBrojOsoba(int $max): self
    {
        return new self("Broj osoba mora biti između 1 i {$max}.");
    }

    public static function dug(): self
    {
        return new self('Rezervacija nije moguća dok postoji dugovanje za stan.');
    }

    public static function blokiran(): self
    {
        return new self('Izabrani termin je blokiran.');
    }

    public static function kvotaPopunjena(int $max): self
    {
        return new self("Dostigli ste maksimum od {$max} rezervacija u periodu.");
    }

    public static function duplikat(): self
    {
        return new self('Već imate rezervaciju za ovaj termin.');
    }

    public static function slotPun(): self
    {
        return new self('Termin je popunjen i ne može se osloboditi mesto.');
    }

    public static function neaktivnaRezervacija(): self
    {
        return new self('Rezervacija je već otkazana ili izmenjena.');
    }
}
