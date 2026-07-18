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

    /**
     * Available days for the modal date selector.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function danOpcije(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $opcije = [];

        for ($i = 0; $i <= $this->config->horizont_dana; $i++) {
            $d = $today->addDays($i);
            $opcije[$d->format('Y-m-d')] = $this->formatDatum($d);
        }

        return $opcije;
    }

    /**
     * Slot windows for the modal slot selector.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function slotOpcije(): array
    {
        $opcije = [];

        foreach ($this->config->slots() as $index => $window) {
            $opcije[$index] = $window['start'].'–'.$window['end'];
        }

        return $opcije;
    }

    public function openReserve(string $datum, int $slot): void
    {
        $this->reset('changingId');
        $this->reserveDatum = $datum;
        $this->reserveSlot = $slot;
        $this->reserveOsoba = 1;
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
        $dani = ['ned', 'pon', 'uto', 'sre', 'čet', 'pet', 'sub'];

        return $dani[(int) $datum->format('w')].' '.$datum->format('d.m.');
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Spa rezervacije') }}</flux:heading>

    <flux:card class="flex flex-col gap-1">
        <flux:heading size="lg">{{ __('Zdravo') }}, {{ $owner->ime }}!</flux:heading>
        <flux:text>
            {{ __('Stan') }} <strong>{{ $owner->stan->broj }}</strong>,
            {{ $owner->stan->zgrada->naziv }}
        </flux:text>
        <flux:text class="text-sm">
            {{ __('Iskorišćeno') }}: {{ $this->mojeRezervacije->count() }}/{{ $config->max_rez_7d }}
            {{ __('rezervacija') }} · {{ __('najviše') }} {{ $config->max_osoba }} {{ __('osoba po terminu') }}
        </flux:text>
    </flux:card>

    {{-- Moje rezervacije --}}
    <div class="flex flex-col gap-2">
        <flux:heading size="lg">{{ __('Moje rezervacije') }}</flux:heading>

        @forelse ($this->mojeRezervacije as $rez)
            <flux:card class="flex items-center justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <flux:text class="font-medium">
                        {{ $this->formatDatum(\Carbon\CarbonImmutable::instance($rez->datum)) }},
                        {{ $config->slotWindow($rez->slot_index)['start'] }}–{{ $config->slotWindow($rez->slot_index)['end'] }}
                    </flux:text>
                    <div class="flex items-center gap-2">
                        @if ($rez->je_trajna)
                            <flux:badge size="sm" color="green">{{ __('Trajna') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">{{ __('Uslovna') }}</flux:badge>
                        @endif
                        <flux:text class="text-sm">{{ $rez->broj_osoba }} {{ __('os.') }}</flux:text>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <flux:button size="sm" variant="ghost" icon="qr-code" wire:click="openQr({{ $rez->id }})">
                        {{ __('QR') }}
                    </flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="openChange({{ $rez->id }})">
                        {{ __('Izmeni') }}
                    </flux:button>
                    <flux:button size="sm" variant="danger"
                        wire:click="cancel({{ $rez->id }})"
                        wire:confirm="{{ __('Otkazati ovu rezervaciju?') }}">
                        {{ __('Otkaži') }}
                    </flux:button>
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
        <flux:heading size="lg">{{ __('Slobodni termini') }}</flux:heading>

        @foreach ($this->dani as $dan)
            <flux:card class="flex flex-col gap-2">
                <flux:heading size="sm">{{ $this->formatDatum($dan['datum']) }}</flux:heading>

                <div class="flex flex-col gap-2">
                    @foreach ($dan['slotovi'] as $slot)
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                            <div class="flex flex-col">
                                <flux:text class="font-medium">{{ $slot['label'] }}</flux:text>
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

            <flux:select wire:model="reserveDatum" :label="__('Datum')">
                @foreach ($this->danOpcije as $vrednost => $tekst)
                    <flux:select.option value="{{ $vrednost }}">{{ $tekst }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="reserveSlot" :label="__('Termin')">
                @foreach ($this->slotOpcije as $vrednost => $tekst)
                    <flux:select.option value="{{ $vrednost }}">{{ $tekst }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="reserveOsoba" :label="__('Broj osoba')">
                @for ($i = 1; $i <= $config->max_osoba; $i++)
                    <flux:select.option value="{{ $i }}">{{ $i }}</flux:select.option>
                @endfor
            </flux:select>

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
</div>
