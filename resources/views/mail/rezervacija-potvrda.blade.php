<x-mail::message>
# Rezervacija potvrđena

Poštovani/a {{ $booking->vlasnik?->ime }},

Vaša rezervacija spa termina je uspešno kreirana.

**Termin:** {{ $termin }}
**Broj osoba:** {{ $booking->broj_osoba }}

Vidimo se u spa centru!

Hvala,<br>
{{ config('app.name') }}
</x-mail::message>
