<x-mail::message>
# Podsetnik za spa termin

Poštovani/a {{ $booking->vlasnik?->ime }},

Podsećamo vas da vaš spa termin počinje uskoro.

**Termin:** {{ $termin }}
**Broj osoba:** {{ $booking->broj_osoba }}
**Kod za prijavu:** {{ $booking->kratki_kod }}

Na ulazu u spa pokažite QR kod iz aplikacije ili navedite kod za prijavu.

Vidimo se u spa centru!

Hvala,<br>
{{ config('app.name') }}
</x-mail::message>
