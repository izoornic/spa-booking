<x-mail::message>
# Rezervacija pomerena

Poštovani/a {{ $booking->vlasnik?->ime }},

Zbog rasporeda kapaciteta, vaša spa rezervacija je pomerena na novi termin.

**Novi termin:** {{ $termin }}
**Broj osoba:** {{ $booking->broj_osoba }}

Ako vam novi termin ne odgovara, možete ga otkazati ili izmeniti.

Hvala na razumevanju,<br>
{{ config('app.name') }}
</x-mail::message>
