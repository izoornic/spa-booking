# Changelog

Sve značajne izmene na aplikaciji beleže se u ovom fajlu.

**Pravilo:** uz svaki update podiže se verzija po [semantičkom verzionisanju](https://semver.org/lang/sr/)
(`MAJOR.MINOR.PATCH`) i **ista verzija se upisuje u `config/global.php`** (ključ `version`,
format `'v 1.1.0'`) — odatle se prikazuje u podnožju aplikacije.

- `MAJOR` — nekompatibilna promena (npr. promena šeme baze koja traži migraciju podataka)
- `MINOR` — nova funkcionalnost bez rušenja postojećeg
- `PATCH` — ispravke grešaka i sitne dorade

## [1.1.1] — 2026-08-18

### Izmenjeno

- **Obaveštenje o novoj rezervaciji šalje se domaru umesto upravniku.** Mejl „Nova spa rezervacija“
  sada dobijaju svi korisnici sa ulogom *domar* (koji imaju email adresu); upravnik ga više ne
  dobija. Interno: `RezervacijaObavestenjeUpravniku` → `RezervacijaObavestenjeDomaru`, šablon
  `mail.rezervacija-upravnik` → `mail.rezervacija-domar`.

## [1.1.0] — 2026-08-17

### Dodato

- **Upravnik — grafički prikaz rezervacija** po danima i terminima (`/upravnik/rezervacije`):
  prekidač **Kalendar / Tabela**, kartica po danu kroz ceo horizont rezervisanja, traka kapaciteta
  sa podelom trajna / uslovna / slobodno, oznaka blokiranih termina i čipovi rezervacija
  („Stan X · N“) obojeni po tipu rezervacije. Tabela sa otkazivanjem rezervacije ostaje kao
  alternativni prikaz.
- **Domar — grafički prikaz zauzeća** na pregledu za danas i sutra: legenda i traka kapaciteta
  na svakom terminu, kao kod vlasnika.
- Zajedničke Blade komponente `x-spa.swatch`, `x-spa.kapacitet` i `x-spa.legenda` — jedan izvor
  istine za boje zauzeća na sve tri uloge (vlasnik, domar, upravnik).

### Izmenjeno

- Domarov pregled prikazuje pun naziv termina („1. termin 12:00–15:00“), isto kao vlasnički prikaz.
- Kartice dana u upravnikovom kalendaru vizuelno izdvojene (tamnija podloga i senka), a boksovi
  termina dobili svetlu podlogu radi čitljivosti u oba teme.
- Vlasnička stranica prebačena na zajedničke komponente zauzeća (izgled nepromenjen).

## [1.0.0] — 2026-07-30

Prvo produkciono izdanje.

### Dodato

- Rezervacioni engine: termini iz radnog vremena, kapacitet po terminu, kvota po stanu,
  pravedna raspodela (trajne i uslovne rezervacije, automatsko pomeranje) i email obaveštenja.
- Pristup vlasnika bez lozinke — lični QR/token link, 7-dnevni kalendar sa rezervisanjem,
  izmenom i otkazivanjem termina.
- Domarska aplikacija: pregled zauzeća, skeniranje QR koda kamerom, unos kratkog koda,
  evidencija dolaska i „nije se pojavio“.
- Panel upravnika: pregled rezervacija, blokade termina sa obaveštavanjem pogođenih vlasnika,
  konfiguracija spa centra, izdavanje QR kodova vlasnicima i dashboard sa pregledom.
- Prijava osoblja sa preusmeravanjem po ulozi, branding i korisničko uputstvo za vlasnike.
- Deploy na cPanel preko Git-a (build asseti u repozitorijumu), import vlasnika iz postojeće
  baze, cron za queue worker i podsetnike.
