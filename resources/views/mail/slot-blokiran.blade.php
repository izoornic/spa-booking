<x-mail::message>
# Termin je zatvoren

Poštovani/a {{ $booking->vlasnik?->ime }},

Nažalost, vaš spa termin je otkazan jer je upravnik zatvorio taj termin.

**Termin:** {{ $termin }}
**Broj osoba:** {{ $booking->broj_osoba }}
@if ($razlog)
**Razlog:** {{ $razlog }}
@endif

Možete napraviti novu rezervaciju za neki drugi slobodan termin.

Izvinjavamo se zbog neprijatnosti,<br>
{{ config('app.name') }}
</x-mail::message>
