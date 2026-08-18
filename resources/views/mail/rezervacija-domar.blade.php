<x-mail::message>
# Nova spa rezervacija

Nova rezervacija je kreirana u spa centru.

**Stan:** {{ $booking->stan->broj }}
**Vlasnik:** {{ $booking->vlasnik?->punoIme() }}
**Termin:** {{ $termin }}
**Broj osoba:** {{ $booking->broj_osoba }}

Pozdrav,<br>
{{ config('app.name') }}
</x-mail::message>
