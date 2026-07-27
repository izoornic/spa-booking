<?php

namespace App\Services;

use App\Enums\BookingState;
use App\Enums\StaffRole;
use App\Exceptions\BookingException;
use App\Mail\RezervacijaObavestenjeUpravniku;
use App\Mail\RezervacijaOtkazana;
use App\Mail\RezervacijaPomerena;
use App\Mail\RezervacijaPotvrda;
use App\Mail\SlotBlokiranObavestenje;
use App\Models\SpaBlokada;
use App\Models\SpaBooking;
use App\Models\SpaConfig;
use App\Models\Stan;
use App\Models\User;
use App\Models\Vlasnik;
use App\Models\Zgrada;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Core spa reservation engine: enforces fair-share rules (trajna/uslovna,
 * displacement of conditional bookings, auto-move) inside a DB transaction.
 */
class SpaBookingService
{
    /**
     * Create a reservation for an apartment, applying capacity & fair-share rules.
     */
    public function reserve(Stan $stan, ?Vlasnik $vlasnik, DateTimeInterface $datum, int $slotIndex, int $brojOsoba): SpaBooking
    {
        $datum = CarbonImmutable::instance($datum)->startOfDay();

        return DB::transaction(function () use ($stan, $vlasnik, $datum, $slotIndex, $brojOsoba) {
            $config = $this->configFor($stan);
            $this->assertBookable($stan, $config, $datum, $slotIndex, $brojOsoba);

            $slot = $this->slotBookings($stan->zgrada_id, $datum, $slotIndex, lock: true);
            $spare = $config->kapacitet - (int) $slot->sum('broj_osoba');

            if ($spare < $brojOsoba) {
                // Only an apartment without any active (permanent) reservation may displace.
                if ($this->activeUpcomingCount($stan) > 0) {
                    throw BookingException::slotPun();
                }

                $this->makeRoom($config, $slot, $datum, $slotIndex, $brojOsoba - $spare);
            }

            $booking = SpaBooking::create([
                'zgrada_id' => $stan->zgrada_id,
                'stan_id' => $stan->id,
                'vlasnik_id' => $vlasnik?->id,
                'datum' => $datum,
                'slot_index' => $slotIndex,
                'broj_osoba' => $brojOsoba,
                'stanje' => BookingState::Booked,
                'je_trajna' => false,
            ]);

            $this->refreshPermanent($stan);

            $fresh = $booking->fresh();

            if ($vlasnik?->email) {
                Mail::to($vlasnik->email)->queue(new RezervacijaPotvrda($fresh));
            }

            $this->notifyManagers($fresh);

            return $fresh;
        });
    }

    /**
     * Email every manager (upravnik) that a new reservation was created.
     */
    private function notifyManagers(SpaBooking $booking): void
    {
        User::where('role', StaffRole::Manager)
            ->whereNotNull('email')
            ->each(function (User $manager) use ($booking): void {
                Mail::to($manager->email)->queue(new RezervacijaObavestenjeUpravniku($booking));
            });
    }

    /**
     * Cancel a reservation and free its capacity immediately.
     */
    public function cancel(SpaBooking $booking): void
    {
        DB::transaction(function () use ($booking) {
            if (! in_array($booking->stanje, BookingState::active(), true)) {
                throw BookingException::neaktivnaRezervacija();
            }

            $config = $this->configFor($booking->stan);
            $start = $config->slotStartAt($booking->datum, $booking->slot_index);

            if (CarbonImmutable::now()->addHours($config->min_sati_pre)->gt($start)) {
                throw BookingException::prekasno($config->min_sati_pre);
            }

            $booking->update(['stanje' => BookingState::Cancelled, 'je_trajna' => false]);
            $this->refreshPermanent($booking->stan);
        });
    }

    /**
     * Change a reservation = cancel the old one then reserve the new details.
     * If the new reservation fails, the whole change is rolled back.
     */
    public function change(SpaBooking $booking, DateTimeInterface $datum, int $slotIndex, int $brojOsoba): SpaBooking
    {
        return DB::transaction(function () use ($booking, $datum, $slotIndex, $brojOsoba) {
            $stan = $booking->stan;
            $vlasnik = $booking->vlasnik;

            $this->cancel($booking);

            return $this->reserve($stan, $vlasnik, $datum, $slotIndex, $brojOsoba);
        });
    }

    /**
     * Cancel any reservation as a manager, bypassing the tenant cut-off window.
     */
    public function cancelAsManager(SpaBooking $booking): void
    {
        DB::transaction(function () use ($booking) {
            if (! in_array($booking->stanje, BookingState::active(), true)) {
                throw BookingException::neaktivnaRezervacija();
            }

            $booking->update(['stanje' => BookingState::Cancelled, 'je_trajna' => false]);
            $this->refreshPermanent($booking->stan);

            if ($booking->vlasnik?->email) {
                Mail::to($booking->vlasnik->email)->queue(new RezervacijaOtkazana($booking->fresh()));
            }
        });
    }

    /**
     * Block a slot (or a whole day when $slotIndex is null): record the blockade and
     * cancel every active reservation it hits, emailing the affected owners.
     */
    public function blokiraj(Zgrada $zgrada, DateTimeInterface $datum, ?int $slotIndex, ?string $razlog, ?User $autor): SpaBlokada
    {
        $datum = CarbonImmutable::instance($datum)->startOfDay();

        return DB::transaction(function () use ($zgrada, $datum, $slotIndex, $razlog, $autor) {
            $blokada = SpaBlokada::create([
                'zgrada_id' => $zgrada->id,
                'datum' => $datum,
                'slot_index' => $slotIndex,
                'razlog' => $razlog,
                'created_by' => $autor?->id,
            ]);

            $affected = SpaBooking::aktivne()
                ->where('zgrada_id', $zgrada->id)
                ->whereDate('datum', $datum)
                ->when($slotIndex !== null, fn ($q) => $q->where('slot_index', $slotIndex))
                ->get();

            foreach ($affected as $booking) {
                $booking->update(['stanje' => BookingState::Cancelled, 'je_trajna' => false]);
                $this->refreshPermanent($booking->stan);

                if ($booking->vlasnik?->email) {
                    Mail::to($booking->vlasnik->email)->queue(new SlotBlokiranObavestenje($booking->fresh(), $razlog));
                }
            }

            return $blokada;
        });
    }

    /**
     * Remove a blockade. Previously cancelled reservations are not restored.
     */
    public function odblokiraj(SpaBlokada $blokada): void
    {
        $blokada->delete();
    }

    /**
     * Re-designate each apartment's nearest upcoming reservation as permanent (trajna).
     */
    public function refreshPermanent(Stan $stan): void
    {
        $config = $this->configFor($stan);
        $now = CarbonImmutable::now();

        $active = SpaBooking::aktivne()->where('stan_id', $stan->id)->get();

        $permanentId = $active
            ->filter(fn (SpaBooking $b): bool => $config->slotEndAt($b->datum, $b->slot_index)->gte($now))
            ->sortBy(fn (SpaBooking $b): int => $config->slotStartAt($b->datum, $b->slot_index)->getTimestamp())
            ->first()?->id;

        foreach ($active as $booking) {
            // A booking is permanent if it is the apartment's nearest upcoming reservation,
            // or if its slot has entered the locked window (see slotZakljucan).
            $should = $booking->id === $permanentId
                || $config->slotZakljucan($booking->datum, $booking->slot_index, $now);

            if ($booking->je_trajna !== $should) {
                $booking->update(['je_trajna' => $should]);
            }
        }
    }

    /**
     * Free the required capacity by displacing conditional bookings, in priority order.
     *
     * @param  Collection<int, SpaBooking>  $slot  locked active bookings in the target slot
     */
    private function makeRoom(SpaConfig $config, Collection $slot, CarbonImmutable $datum, int $slotIndex, int $needed): void
    {
        $terminStart = $config->slotStartAt($datum, $slotIndex);

        // Once the target slot is locked, nothing in it may be displaced — even a
        // reservation whose je_trajna flag has not yet been refreshed.
        if ($config->slotZakljucan($datum, $slotIndex, CarbonImmutable::now())) {
            throw BookingException::slotPun();
        }

        $conditionals = $slot
            ->reject(fn (SpaBooking $b): bool => $b->je_trajna)
            ->sort(fn (SpaBooking $a, SpaBooking $b): int => $this->comparePriority($config, $terminStart, $a, $b))
            ->values();

        $freed = 0;

        foreach ($conditionals as $booking) {
            if ($freed >= $needed) {
                break;
            }

            $freed += $booking->broj_osoba;
            $this->displace($config, $booking, $datum, $slotIndex);
        }

        if ($freed < $needed) {
            throw BookingException::slotPun();
        }
    }

    /**
     * Ordering: apartment with the nearest PRECEDING reservation is displaced first;
     * apartments with no preceding reservation are displaced last.
     */
    private function comparePriority(SpaConfig $config, CarbonImmutable $terminStart, SpaBooking $a, SpaBooking $b): int
    {
        $da = $this->precedingDistance($config, $terminStart, $a);
        $db = $this->precedingDistance($config, $terminStart, $b);

        if ($da !== $db) {
            if ($da === null) {
                return 1;
            }
            if ($db === null) {
                return -1;
            }

            return $da <=> $db;
        }

        return [$a->broj_osoba, $a->id] <=> [$b->broj_osoba, $b->id];
    }

    /**
     * Minutes between the apartment's nearest reservation strictly before the termin and the termin.
     * Null when the apartment has no preceding reservation.
     */
    private function precedingDistance(SpaConfig $config, CarbonImmutable $terminStart, SpaBooking $booking): ?int
    {
        $latest = null;

        $others = SpaBooking::aktivne()
            ->where('stan_id', $booking->stan_id)
            ->where('id', '!=', $booking->id)
            ->get();

        foreach ($others as $other) {
            $start = $config->slotStartAt($other->datum, $other->slot_index);

            if ($start->lt($terminStart) && ($latest === null || $start->gt($latest))) {
                $latest = $start;
            }
        }

        return $latest === null ? null : (int) $latest->diffInMinutes($terminStart);
    }

    /**
     * Move a displaced conditional booking to the first free slot, or cancel it.
     */
    private function displace(SpaConfig $config, SpaBooking $booking, CarbonImmutable $izbegniDatum, int $izbegniSlot): void
    {
        $booking->update(['stanje' => BookingState::Moved, 'je_trajna' => false]);

        $target = $this->firstFreeSlot($config, $booking, $izbegniDatum, $izbegniSlot);

        if ($target !== null) {
            $novi = SpaBooking::create([
                'zgrada_id' => $booking->zgrada_id,
                'stan_id' => $booking->stan_id,
                'vlasnik_id' => $booking->vlasnik_id,
                'datum' => $target['datum'],
                'slot_index' => $target['slot'],
                'broj_osoba' => $booking->broj_osoba,
                'stanje' => BookingState::Booked,
                'je_trajna' => false,
            ]);

            $this->refreshPermanent($booking->stan);

            if ($booking->vlasnik?->email) {
                Mail::to($booking->vlasnik->email)->queue(new RezervacijaPomerena($novi->fresh()));
            }

            return;
        }

        $booking->update(['stanje' => BookingState::Cancelled]);
        $this->refreshPermanent($booking->stan);

        if ($booking->vlasnik?->email) {
            Mail::to($booking->vlasnik->email)->queue(new RezervacijaOtkazana($booking->fresh()));
        }
    }

    /**
     * First chronologically free slot (with spare capacity, no chained displacement).
     *
     * @return array{datum: CarbonImmutable, slot: int}|null
     */
    private function firstFreeSlot(SpaConfig $config, SpaBooking $booking, CarbonImmutable $izbegniDatum, int $izbegniSlot): ?array
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();
        $horizonEnd = $today->addDays($config->horizont_dana);
        $earliest = $now->addHours($config->min_sati_pre);

        for ($d = $today; $d->lte($horizonEnd); $d = $d->addDay()) {
            for ($s = 1; $s <= $config->broj_slotova; $s++) {
                if ($d->isSameDay($izbegniDatum) && $s === $izbegniSlot) {
                    continue;
                }

                if ($config->slotStartAt($d, $s)->lt($earliest)) {
                    continue;
                }

                if ($this->isBlocked($booking->zgrada_id, $d, $s)) {
                    continue;
                }

                $alreadyBooked = SpaBooking::aktivne()
                    ->uSlotu($booking->zgrada_id, $d, $s)
                    ->where('stan_id', $booking->stan_id)
                    ->exists();

                if ($alreadyBooked) {
                    continue;
                }

                $spare = $config->kapacitet - (int) $this->slotBookings($booking->zgrada_id, $d, $s)->sum('broj_osoba');

                if ($spare >= $booking->broj_osoba) {
                    return ['datum' => $d, 'slot' => $s];
                }
            }
        }

        return null;
    }

    /**
     * Validate all booking preconditions; throws BookingException on the first failure.
     */
    private function assertBookable(Stan $stan, SpaConfig $config, CarbonImmutable $datum, int $slotIndex, int $brojOsoba): void
    {
        if (! $config->aktivan) {
            throw BookingException::konfiguracijaNeaktivna();
        }

        if ($slotIndex < 1 || $slotIndex > $config->broj_slotova) {
            throw BookingException::nepostojeciSlot();
        }

        $now = CarbonImmutable::now();
        $today = $now->startOfDay();
        $horizonEnd = $today->addDays($config->horizont_dana);

        if ($datum->lt($today) || $datum->gt($horizonEnd)) {
            throw BookingException::vanHorizonta($config->horizont_dana);
        }

        if ($config->slotStartAt($datum, $slotIndex)->lt($now->addHours($config->min_sati_pre))) {
            throw BookingException::prekasno($config->min_sati_pre);
        }

        if ($brojOsoba < 1 || $brojOsoba > $config->max_osoba) {
            throw BookingException::nevalidanBrojOsoba($config->max_osoba);
        }

        if ($config->blokiraj_dug && $stan->ima_dug) {
            throw BookingException::dug();
        }

        if ($this->isBlocked($stan->zgrada_id, $datum, $slotIndex)) {
            throw BookingException::blokiran();
        }

        $count = SpaBooking::aktivne()
            ->where('stan_id', $stan->id)
            ->nadolazece($today)
            ->count();

        if ($count >= $config->max_rez_7d) {
            throw BookingException::kvotaPopunjena($config->max_rez_7d);
        }

        $duplikat = SpaBooking::aktivne()
            ->uSlotu($stan->zgrada_id, $datum, $slotIndex)
            ->where('stan_id', $stan->id)
            ->exists();

        if ($duplikat) {
            throw BookingException::duplikat();
        }
    }

    /**
     * Locked/unlocked active bookings in a specific slot.
     *
     * @return Collection<int, SpaBooking>
     */
    private function slotBookings(int $zgradaId, CarbonImmutable $datum, int $slotIndex, bool $lock = false): Collection
    {
        $query = SpaBooking::aktivne()->uSlotu($zgradaId, $datum, $slotIndex);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function isBlocked(int $zgradaId, CarbonImmutable $datum, int $slotIndex): bool
    {
        return SpaBlokada::where('zgrada_id', $zgradaId)
            ->whereDate('datum', $datum)
            ->where(fn ($q) => $q->whereNull('slot_index')->orWhere('slot_index', $slotIndex))
            ->exists();
    }

    private function activeUpcomingCount(Stan $stan): int
    {
        $config = $this->configFor($stan);
        $now = CarbonImmutable::now();

        return SpaBooking::aktivne()
            ->where('stan_id', $stan->id)
            ->get()
            ->filter(fn (SpaBooking $b): bool => $config->slotEndAt($b->datum, $b->slot_index)->gte($now))
            ->count();
    }

    private function configFor(Stan $stan): SpaConfig
    {
        $stan->loadMissing('zgrada.config');
        $config = $stan->zgrada?->config;

        if ($config === null) {
            throw BookingException::konfiguracijaNeaktivna();
        }

        return $config;
    }
}
