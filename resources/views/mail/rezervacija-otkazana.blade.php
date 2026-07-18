<x-mail::message>
# Rezervacija otkazana

Poštovani/a {{ $booking->vlasnik?->ime }},

Nažalost, vaša spa rezervacija je morala biti otkazana jer nije bilo slobodnog termina za pomeranje.

**Otkazani termin:** {{ $termin }}
**Broj osoba:** {{ $booking->broj_osoba }}

Slobodno napravite novu rezervaciju za neki od dostupnih termina.

Izvinjavamo se zbog neprijatnosti,<br>
{{ config('app.name') }}
</x-mail::message>
