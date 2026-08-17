<?php

use App\Enums\BookingState;
use App\Exceptions\BookingException;
use App\Http\Middleware\EnsureOwner;
use App\Models\SpaBlokada;
use App\Models\SpaBooking;
use App\Models\SpaConfig;
use App\Models\Stan;
use App\Models\Vlasnik;
use App\Services\SpaBookingService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.owner')] #[Title('Spa rezervacije')] class extends Component {
    public Vlasnik $owner;

    public SpaConfig $config;

    /** Reserve/change modal state. */
    public bool $showReserve = false;

    public ?int $changingId = null;

    /** Info modal state. */
    public bool $showInfo = false;

    /** QR modal state. */
    public bool $showQr = false;

    public string $qrUrl = '';

    public string $qrKod = '';

    #[Validate('required|date')]
    public string $reserveDatum = '';

    #[Validate('required|integer|min:1')]
    public int $reserveSlot = 1;

    #[Validate('required|integer|min:1')]
    public int $reserveOsoba = 1;

    public function mount(): void
    {
        $this->owner = Vlasnik::with('stan.zgrada.config')
            ->findOrFail(session(EnsureOwner::SESSION_KEY));

        $config = $this->owner->stan->zgrada->config;

        abort_if($config === null, 404, 'Konfiguracija spa centra nije podešena.');

        $this->config = $config;
    }

    /**
     * The apartment booking on behalf of the current owner.
     */
    protected function stan(): Stan
    {
        return $this->owner->stan;
    }

    /**
     * 7-day grid: each day with its slots and availability for this apartment.
     *
     * @return array<int, array{datum: CarbonImmutable, slotovi: array<int, array<string, mixed>>}>
     */
    #[Computed]
    public function dani(): array
    {
        $config = $this->config;
        $stan = $this->stan();
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();
        $horizonEnd = $today->addDays($config->horizont_dana);
        $earliest = $now->addHours($config->min_sati_pre);

        /** @var Collection<string, Collection<int, SpaBooking>> $bookings */
        $bookings = SpaBooking::aktivne()
            ->where('zgrada_id', $stan->zgrada_id)
            ->whereBetween('datum', [$today, $horizonEnd])
            ->get()
            ->groupBy(fn (SpaBooking $b): string => $b->datum->format('Y-m-d').'#'.$b->slot_index);

        $blokade = SpaBlokada::where('zgrada_id', $stan->zgrada_id)
            ->whereBetween('datum', [$today, $horizonEnd])
            ->get()
            ->groupBy(fn (SpaBlokada $b): string => $b->datum->format('Y-m-d'));

        $canDisplace = $this->aktivnihRezervacija() === 0;

        $dani = [];

        for ($d = $today; $d->lte($horizonEnd); $d = $d->addDay()) {
            $dayKey = $d->format('Y-m-d');
            $slotovi = [];

            for ($s = 1; $s <= $config->broj_slotova; $s++) {
                $window = $config->slotWindow($s);
                $start = $config->slotStartAt($d, $s);
                $inSlot = $bookings->get($dayKey.'#'.$s, collect());
                $zauzeto = (int) $inSlot->sum('broj_osoba');
                $trajne = (int) $inSlot->where('je_trajna', true)->sum('broj_osoba');
                $uslovne = $zauzeto - $trajne;
                $spare = $config->kapacitet - $zauzeto;

                $mojaRez = $inSlot->firstWhere('stan_id', $stan->id);
                $blokirano = ($blokade->get($dayKey) ?? collect())
                    ->contains(fn (SpaBlokada $b): bool => $b->slot_index === null || $b->slot_index === $s);
                $prekasno = $start->lt($earliest);

                $mozeRezervisati = ! $blokirano
                    && ! $prekasno
                    && $mojaRez === null
                    && ($spare >= 1 || $canDisplace);

                $slotovi[$s] = [
                    'index' => $s,
                    'label' => $window['start'].'–'.$window['end'],
                    'zauzeto' => $zauzeto,
                    'trajne' => $trajne,
                    'uslovne' => $uslovne,
                    'kapacitet' => $config->kapacitet,
                    'spare' => $spare,
                    'moja_rezervacija' => $mojaRez,
                    'blokirano' => $blokirano,
                    'prekasno' => $prekasno,
                    'moze_rezervisati' => $mozeRezervisati,
                ];
            }

            $dani[] = ['datum' => $d, 'slotovi' => $slotovi];
        }

        return $dani;
    }

    /**
     * This apartment's active upcoming reservations (nearest first).
     *
     * @return Collection<int, SpaBooking>
     */
    #[Computed]
    public function mojeRezervacije(): Collection
    {
        $config = $this->config;
        $now = CarbonImmutable::now();

        return SpaBooking::aktivne()
            ->where('stan_id', $this->stan()->id)
            ->get()
            ->filter(fn (SpaBooking $b): bool => $config->slotEndAt($b->datum, $b->slot_index)->gte($now))
            ->sortBy(fn (SpaBooking $b): int => $config->slotStartAt($b->datum, $b->slot_index)->getTimestamp())
            ->values();
    }

    /**
     * How many upcoming active reservations this apartment already holds.
     */
    protected function aktivnihRezervacija(): int
    {
        return $this->mojeRezervacije()->count();
    }

    #[Computed]
    public function dostignutaKvota(): bool
    {
        return $this->aktivnihRezervacija() >= $this->config->max_rez_7d;
    }

    public function openReserve(string $datum, int $slot): void
    {
        $this->reset('changingId');
        $this->reserveDatum = $datum;
        $this->reserveSlot = $slot;
        $this->reserveOsoba = 0;
        $this->resetValidation();
        $this->showReserve = true;
    }

    public function openChange(int $bookingId): void
    {
        $booking = SpaBooking::where('stan_id', $this->stan()->id)->findOrFail($bookingId);

        $this->changingId = $booking->id;
        $this->reserveDatum = $booking->datum->format('Y-m-d');
        $this->reserveSlot = $booking->slot_index;
        $this->reserveOsoba = $booking->broj_osoba;
        $this->resetValidation();
        $this->showReserve = true;
    }

    public function openQr(int $bookingId): void
    {
        $booking = SpaBooking::where('stan_id', $this->stan()->id)->findOrFail($bookingId);

        $this->qrUrl = route('domar.rezervacija', ['kod' => $booking->qr_token]);
        $this->qrKod = (string) $booking->kratki_kod;
        $this->showQr = true;
    }

    public function reserve(SpaBookingService $service): void
    {
        $this->validate();

        try {
            $datum = CarbonImmutable::parse($this->reserveDatum);

            if ($this->changingId !== null) {
                $booking = SpaBooking::where('stan_id', $this->stan()->id)->findOrFail($this->changingId);
                $service->change($booking, $datum, $this->reserveSlot, $this->reserveOsoba);
                Flux::toast(variant: 'success', text: 'Rezervacija je izmenjena.');
            } else {
                $service->reserve($this->stan(), $this->owner, $datum, $this->reserveSlot, $this->reserveOsoba);
                Flux::toast(variant: 'success', text: 'Rezervacija je uspešno kreirana.');
            }

            $this->showReserve = false;
        } catch (BookingException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function cancel(int $bookingId, SpaBookingService $service): void
    {
        $booking = SpaBooking::where('stan_id', $this->stan()->id)->findOrFail($bookingId);

        try {
            $service->cancel($booking);
            Flux::toast(variant: 'success', text: 'Rezervacija je otkazana.');
        } catch (BookingException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function formatDatum(CarbonImmutable $datum): string
    {
        $dani = ['nedelja', 'ponedeljak', 'utorak', 'sreda', 'četvrtak', 'petak', 'subota'];

        return $dani[(int) $datum->format('w')].' '.$datum->format('d.m.');
    }
}; ?>

<div class="flex flex-col gap-6">

    <flux:card class="flex flex-col gap-1">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Zdravo') }}, {{ $owner->ime }}!</flux:heading>
             <button type="button" wire:click="$set('showInfo', true)" class="flex border border-zinc-200 text-sky-600 dark:text-sky-400 px-1 py-1 dark:border-zinc-700 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800">
                <x-heroicon-c-information-circle class="w-4 h-4 mr-1 mt-1" />
                {{ __('Info') }}
            </button>
        </div>
        <flux:text>
            {{ __('Stan') }} <strong>{{ $owner->stan->broj }}</strong>,
            {{ $owner->stan->zgrada->naziv }}
        </flux:text>
       
        <flux:text class="text-sm">
            {{ __('Iskorišćeno rezervacija: ') }}: <strong>{{ $this->mojeRezervacije->count() }}</strong> od <strong>{{ $config->max_rez_7d }}</strong> 
            <br /> {{ __('najviše') }} {{ $config->max_osoba }} {{ __('osoba po terminu') }}
        </flux:text>
    </flux:card>

    {{-- Moje rezervacije --}}
    <div class="flex flex-col gap-2">
        <flux:heading size="lg" class="flex gap-2">
            <x-heroicon-o-check-badge class="h-6 w-6" />
            {{ __('Moje rezervacije') }}
        </flux:heading>

        @forelse ($this->mojeRezervacije as $rez)
            <flux:card class="!p-3 shadow-md">
                <div class="flex items-center justify-between gap-3">
                    <flux:text class="font-medium">
                        <strong>{{ $this->formatDatum(\Carbon\CarbonImmutable::instance($rez->datum)) }}</strong>
                    </flux:text>

                    <flux:text class="font-medium">
                        {{ $rez->slot_index }}. {{ __('termin') }}
                        {{ $config->slotWindow($rez->slot_index)['start'] }}–{{ $config->slotWindow($rez->slot_index)['end'] }}
                    </flux:text>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                    <div class="flex flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($rez->je_trajna)
                                <flux:badge size="sm" color="green">{{ __('Trajna') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('Uslovna') }}</flux:badge>
                            @endif
                            <div class="flex">
                                @foreach (range(1, $rez->broj_osoba) as $i)
                                    <x-heroicon-s-user class="w-3 h-3 text-sky-600 dark:text-sky-400 px-0"  />
                                @endforeach
                            </div>
                            <flux:text class="text-sm">{{ $rez->broj_osoba }} @if ($rez->broj_osoba == 1 || $rez->broj_osoba > 4) {{ __('osoba') }} @else {{ __('osobe') }} @endif</flux:text>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 ml-auto">
                        <flux:button size="sm" variant="ghost" icon="qr-code" wire:click="openQr({{ $rez->id }})">
                            {{ __('QR') }}
                        </flux:button>
                        <flux:button size="sm" variant="outline" wire:click="openChange({{ $rez->id }})">
                            <x-heroicon-c-ellipsis-vertical class="h-5 w-5" />
                        </flux:button>
                        <flux:button size="sm" class="border border-zinc-300 dark:border-zinc-600"
                            wire:click="cancel({{ $rez->id }})"
                            wire:confirm="{{ __('Otkaži ovu rezervaciju?') }}">
                            <x-heroicon-o-trash class="h-5 w-5 text-red-600 dark:text-red-400" />
                        </flux:button>
                    </div>
                </div>
            </flux:card>
        @empty
            <flux:text class="text-sm text-zinc-500">{{ __('Nemate aktivnih rezervacija.') }}</flux:text>
        @endforelse

        @if ($this->dostignutaKvota)
            <flux:callout variant="warning" icon="exclamation-triangle" class="text-sm">
                {{ __('Dostigli ste maksimalan broj rezervacija.') }}
            </flux:callout>
        @endif
    </div>

    {{-- Kalendar --}}
    <div class="flex flex-col gap-3">
        <flux:heading size="lg" class="flex gap-2">
            <x-heroicon-o-calendar-days class="h-6 w-6" />
            {{ __('Termini') }}
        </flux:heading>

        <x-spa.legenda />

        @foreach ($this->dani as $dan)
            <flux:card class="!p-3 flex flex-col gap-2 shadow-md">
                <flux:heading size="lg" class="flex"><x-heroicon-o-calendar class="h-5 w-5 mr-2 mt-1" />{{ $this->formatDatum($dan['datum']) }}</flux:heading>

                <div class="flex flex-col gap-2">
                    @foreach ($dan['slotovi'] as $slot)
                        <div class="border border-zinc-200 px-3 py-2 dark:border-zinc-700 rounded-lg">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex flex-col">
                                    <flux:text class="font-medium">{{ $slot['index'] }}. termin {{ $slot['label'] }}</flux:text>
                                    <flux:text class="text-xs text-zinc-500">
                                        {{ $slot['zauzeto'] }}/{{ $slot['kapacitet'] }} {{ __('mesta') }}
                                    </flux:text>
                                </div>

                                @if ($slot['moja_rezervacija'])
                                    <flux:badge size="sm" color="blue">{{ __('Vaša rezervacija') }}</flux:badge>
                                @elseif ($slot['blokirano'])
                                    <flux:badge size="sm" color="zinc">{{ __('Blokiran') }}</flux:badge>
                                @elseif ($slot['prekasno'])
                                    <flux:badge size="sm" color="zinc">{{ __('Prošlo') }}</flux:badge>
                                @elseif ($slot['spare'] < 1 && ! $slot['moze_rezervisati'])
                                    <flux:badge size="sm" color="red">{{ __('Popunjeno') }}</flux:badge>
                                @elseif ($this->dostignutaKvota)
                                    <flux:badge size="sm" color="zinc">—</flux:badge>
                                @else
                                    <flux:button size="sm" variant="primary"
                                        wire:click="openReserve('{{ $dan['datum']->format('Y-m-d') }}', {{ $slot['index'] }})">
                                        {{ __('Rezerviši') }}
                                    </flux:button>
                                @endif
                            </div>
                            <x-spa.kapacitet class="mt-2"
                                :trajne="$slot['trajne']"
                                :uslovne="$slot['uslovne']"
                                :kapacitet="$slot['kapacitet']" />
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endforeach
    </div>

    {{-- Reserve / change modal --}}
    <flux:modal wire:model.self="showReserve" class="md:w-96">
        <form wire:submit="reserve" class="flex flex-col gap-4">
            <flux:heading size="lg">
                {{ $changingId ? __('Izmena rezervacije') : __('Nova rezervacija') }}
            </flux:heading>

            {{-- Termin je već izabran iz kalendara — ovde se ne menja. --}}
            <flux:text class="text-sm text-zinc-500">
                {{ $this->formatDatum(\Carbon\CarbonImmutable::parse($reserveDatum)) }}<br />
                {{ $reserveSlot }}. termin  - {{ $config->slotWindow($reserveSlot)['start'] }}–{{ $config->slotWindow($reserveSlot)['end'] }}
            </flux:text>

            {{-- Broj osoba: mreža dugmića sa User ikonom, ponaša se kao rating. --}}
            <flux:field>
                <div x-data="{ osoba: $wire.entangle('reserveOsoba'), hover: 0 }" class="flex flex-col gap-2">
                    <flux:label>
                        {{ __('Broj osoba: ') }}&nbsp; 
                        <span x-text="hover || osoba" class="font-semibold text-zinc-900 dark:text-white"></span>
                    </flux:label>
                    <div class="grid grid-cols-5 gap-2">
                        @for ($i = 1; $i <= $config->max_osoba; $i++)
                            <button type="button"
                                @click="osoba = {{ $i }}"
                                @mouseenter="hover = {{ $i }}"
                                @mouseleave="hover = 0"
                                :aria-pressed="osoba === {{ $i }}"
                                aria-label="{{ $i }} {{ __('osoba') }}"
                                :class="(hover || osoba) >= {{ $i }}
                                    ? 'border-sky-600 bg-white text-sky-600 dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                                    : 'border-zinc-200 text-zinc-400 dark:border-zinc-700 dark:text-zinc-500'"
                                class="flex flex-col items-center gap-1 rounded-lg border p-2 transition-colors">
                                <x-heroicon-s-user class="h-5 w-5" />
                            </button>
                        @endfor
                    </div>
                    <flux:error name="reserveOsoba" />
                </div>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" x-on:click="$flux.modal().close()">{{ __('Otkaži') }}</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $changingId ? __('Sačuvaj') : __('Rezerviši') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- QR modal --}}
    <flux:modal wire:model.self="showQr" class="md:w-80">
        <div class="flex flex-col items-center gap-4" wire:key="qr-{{ $qrKod }}"
            x-data="qrCode(@js($qrUrl))" x-init="$nextTick(() => render())">
            <flux:heading size="lg">{{ __('QR za prijavu') }}</flux:heading>
            <canvas x-ref="canvas" class="rounded-lg bg-white p-2"></canvas>
            <div class="text-center">
                <flux:text class="text-sm text-zinc-500">{{ __('Kratki kod') }}</flux:text>
                <div class="font-mono text-2xl font-bold tracking-widest">{{ $qrKod }}</div>
            </div>
            <flux:text class="text-center text-xs text-zinc-400">
                {{ __('Pokažite QR ili kratki kod domaru na ulazu u spa.') }}
            </flux:text>
        </div>
    </flux:modal>

    {{-- Info / kako servis funkcioniše --}}
    <flux:modal wire:model.self="showInfo" class="md:w-[32rem]">
        <div class="flex flex-col gap-5">
            <div class="flex items-center gap-2">
                <x-heroicon-o-information-circle class="h-6 w-6 text-sky-600 dark:text-sky-400" />
                <flux:heading size="lg">{{ __('Kako funkcioniše rezervacija') }}</flux:heading>
            </div>

            <div class="flex flex-col gap-4 text-sm text-zinc-600 dark:text-zinc-300">
                <div class="flex gap-3">
                    <x-heroicon-o-calendar-days class="h-5 w-5 shrink-0 text-sky-600 dark:text-sky-400" />
                    <div>
                        <flux:text class="font-medium text-zinc-900 dark:text-white">{{ __('Termini') }}</flux:text>
                        <p>
                            {{ __('Spa možete rezervisati') }} {{ $config->horizont_dana }} {{ __('dana unapred.') }}
                            {{ __('Svaki dan ima') }} {{ $config->broj_slotova }} {{ __('termina po 3 sata:') }}
                            @for ($s = 1; $s <= $config->broj_slotova; $s++)
                                <strong>{{ $config->slotWindow($s)['start'] }}–{{ $config->slotWindow($s)['end'] }}</strong>@if ($s < $config->broj_slotova), @endif
                            @endfor.
                            {{ __('Rezervisati se može najkasnije') }} {{ $config->min_sati_pre }} {{ __('sata pre početka termina.') }}
                        </p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <x-heroicon-o-users class="h-5 w-5 shrink-0 text-sky-600 dark:text-sky-400" />
                    <div>
                        <flux:text class="font-medium text-zinc-900 dark:text-white">{{ __('Kapacitet i kvota') }}</flux:text>
                        <p>
                            {{ __('U svakom terminu ima') }} <strong>{{ $config->kapacitet }}</strong> {{ __('mesta, deljenih između svih stanova.') }}
                            {{ __('Vaš stan može imati najviše') }} <strong>{{ $config->max_rez_7d }}</strong> {{ __('rezervacija u 7 dana, i najviše') }} <strong>{{ $config->max_osoba }}</strong> {{ __('osoba po terminu.') }}
                        </p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <x-heroicon-o-scale class="h-5 w-5 shrink-0 text-sky-600 dark:text-sky-400" />
                    <div>
                        <flux:text class="font-medium text-zinc-900 dark:text-white">{{ __('Pravedna raspodela') }}</flux:text>
                        <p>
                            {{ __('Vaša prva (najbliža) rezervacija je') }}
                            <flux:badge size="sm" color="green">{{ __('Trajna') }}</flux:badge>
                            {{ __('— zagarantovana je i niko je ne može preuzeti.') }}
                            {{ __('Svaka dodatna rezervacija je') }}
                            <flux:badge size="sm" color="amber">{{ __('Uslovna') }}</flux:badge>
                            {{ __('i može biti pomerena ako stan koji još nema nijednu rezervaciju traži popunjen termin. Ako se to dogodi, dobićete email i rezervacija se automatski prebacuje na prvi sledeći slobodan termin.') }}
                        </p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <x-heroicon-o-qr-code class="h-5 w-5 shrink-0 text-sky-600 dark:text-sky-400" />
                    <div>
                        <flux:text class="font-medium text-zinc-900 dark:text-white">{{ __('Ulazak u spa') }}</flux:text>
                        <p>{{ __('Na ulazu pokažite QR kod ili kratki kod domaru, koji potvrđuje vašu rezervaciju i broj prisutnih osoba.') }}</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" x-on:click="$flux.modal().close()">{{ __('Razumem') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
