<?php

use App\Enums\BookingState;
use App\Exceptions\BookingException;
use App\Models\SpaBlokada;
use App\Models\SpaBooking;
use App\Models\SpaConfig;
use App\Services\SpaBookingService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Rezervacije — upravnik')] class extends Component {
    /** View: kalendar | tabela. */
    #[Url]
    public string $prikaz = 'kalendar';

    /** Filter: nadolazece | danas | sve. */
    #[Url]
    public string $filter = 'nadolazece';

    /**
     * All reservations matching the current filter (newest slot first).
     *
     * @return Collection<int, SpaBooking>
     */
    #[Computed]
    public function rezervacije(): Collection
    {
        $today = CarbonImmutable::now()->startOfDay();

        return SpaBooking::query()
            ->with(['stan', 'vlasnik', 'zgrada.config'])
            ->when($this->filter === 'nadolazece', fn ($q) => $q->whereDate('datum', '>=', $today))
            ->when($this->filter === 'danas', fn ($q) => $q->whereDate('datum', $today))
            ->orderByDesc('datum')
            ->orderBy('slot_index')
            ->limit(300)
            ->get();
    }

    /**
     * Occupancy calendar per building: every day of the booking horizon with its
     * slots, capacity split (trajne/uslovne/free) and the apartments booked in each.
     *
     * @return array<int, array{naziv: string, dani: array<int, array<string, mixed>>}>
     */
    #[Computed]
    public function kalendar(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $zgrade = [];

        foreach (SpaConfig::with('zgrada')->get() as $config) {
            $horizonEnd = $today->addDays($config->horizont_dana);

            /** @var Collection<string, Collection<int, SpaBooking>> $bookings */
            $bookings = SpaBooking::aktivne()
                ->where('zgrada_id', $config->zgrada_id)
                ->whereBetween('datum', [$today, $horizonEnd])
                ->with('stan')
                ->get()
                ->groupBy(fn (SpaBooking $b): string => $b->datum->format('Y-m-d').'#'.$b->slot_index);

            $blokade = SpaBlokada::where('zgrada_id', $config->zgrada_id)
                ->whereBetween('datum', [$today, $horizonEnd])
                ->get()
                ->groupBy(fn (SpaBlokada $b): string => $b->datum->format('Y-m-d'));

            $dani = [];

            for ($d = $today; $d->lte($horizonEnd); $d = $d->addDay()) {
                $dayKey = $d->format('Y-m-d');
                $slotovi = [];

                for ($s = 1; $s <= $config->broj_slotova; $s++) {
                    $window = $config->slotWindow($s);
                    $inSlot = $bookings->get($dayKey.'#'.$s, collect())
                        ->sortByDesc(fn (SpaBooking $b): bool => $b->je_trajna)
                        ->values();

                    $zauzeto = (int) $inSlot->sum('broj_osoba');
                    $trajne = (int) $inSlot->where('je_trajna', true)->sum('broj_osoba');

                    $slotovi[] = [
                        'index' => $s,
                        'label' => $window['start'].'–'.$window['end'],
                        'zauzeto' => $zauzeto,
                        'trajne' => $trajne,
                        'uslovne' => $zauzeto - $trajne,
                        'kapacitet' => $config->kapacitet,
                        'blokirano' => ($blokade->get($dayKey) ?? collect())
                            ->contains(fn (SpaBlokada $b): bool => $b->slot_index === null || $b->slot_index === $s),
                        'rezervacije' => $inSlot,
                    ];
                }

                $dani[] = ['datum' => $d, 'slotovi' => $slotovi];
            }

            $zgrade[] = [
                'naziv' => $config->zgrada?->naziv ?? __('Spa'),
                'dani' => $dani,
            ];
        }

        return $zgrade;
    }

    public function otkazi(int $bookingId, SpaBookingService $service): void
    {
        $booking = SpaBooking::findOrFail($bookingId);

        try {
            $service->cancelAsManager($booking);
            Flux::toast(variant: 'success', text: __('Rezervacija je otkazana.'));
        } catch (BookingException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        unset($this->rezervacije, $this->kalendar);
    }

    public function formatDatum(CarbonImmutable $datum): string
    {
        $dani = ['nedelja', 'ponedeljak', 'utorak', 'sreda', 'četvrtak', 'petak', 'subota'];

        return $dani[(int) $datum->format('w')].' '.$datum->format('d.m.');
    }

    public function termin(SpaBooking $booking): string
    {
        $config = $booking->zgrada?->config;

        if ($config === null) {
            return CarbonImmutable::instance($booking->datum)->format('d.m.Y.');
        }

        $window = $config->slotWindow($booking->slot_index);

        return CarbonImmutable::instance($booking->datum)->format('d.m.Y.').' '.$window['start'].'–'.$window['end'];
    }

    public function stanjeBoja(BookingState $stanje): string
    {
        return match ($stanje) {
            BookingState::Confirmed => 'green',
            BookingState::Booked => 'blue',
            BookingState::NoShow => 'red',
            BookingState::Cancelled => 'zinc',
            BookingState::Moved => 'amber',
        };
    }

    public function jeAktivna(BookingState $stanje): bool
    {
        return in_array($stanje, BookingState::active(), true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Rezervacije') }}</flux:heading>

        <div class="flex items-center gap-2">
            <flux:radio.group wire:model.live="prikaz" variant="segmented" size="sm">
                <flux:radio value="kalendar" icon="calendar-days">{{ __('Kalendar') }}</flux:radio>
                <flux:radio value="tabela" icon="table-cells">{{ __('Tabela') }}</flux:radio>
            </flux:radio.group>

            @if ($prikaz === 'tabela')
                <flux:select wire:model.live="filter" class="max-w-48">
                    <flux:select.option value="nadolazece">{{ __('Nadolazeće') }}</flux:select.option>
                    <flux:select.option value="danas">{{ __('Danas') }}</flux:select.option>
                    <flux:select.option value="sve">{{ __('Sve') }}</flux:select.option>
                </flux:select>
            @endif
        </div>
    </div>

    @if ($prikaz === 'kalendar')
        {{-- Grafički pregled po danima i terminima --}}
        @foreach ($this->kalendar as $zgrada)
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ $zgrada['naziv'] }}</flux:heading>
                    <x-spa.legenda />
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($zgrada['dani'] as $dan)
                        <flux:card class="!p-3 flex flex-col gap-2 bg-zinc-100 dark:bg-zinc-900/40 shadow-md">
                            <flux:heading size="sm" class="flex items-center gap-2">
                                <x-heroicon-o-calendar class="h-4 w-4" />
                                {{ $this->formatDatum($dan['datum']) }}
                            </flux:heading>

                            @foreach ($dan['slotovi'] as $slot)
                                <div @class([
                                    'rounded-lg border border-zinc-200 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-white/5',
                                    'opacity-60' => $slot['blokirano'],
                                ])>
                                    <div class="flex items-center justify-between gap-2">
                                        <flux:text class="text-sm font-medium">
                                            {{ $slot['index'] }}. {{ $slot['label'] }}
                                        </flux:text>

                                        @if ($slot['blokirano'])
                                            <flux:badge size="sm" color="zinc">{{ __('Blokiran') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" :color="$slot['zauzeto'] >= $slot['kapacitet'] ? 'red' : 'zinc'">
                                                {{ $slot['zauzeto'] }}/{{ $slot['kapacitet'] }}
                                            </flux:badge>
                                        @endif
                                    </div>

                                    <x-spa.kapacitet class="mt-2"
                                        :trajne="$slot['trajne']"
                                        :uslovne="$slot['uslovne']"
                                        :kapacitet="$slot['kapacitet']" />

                                    @if ($slot['rezervacije']->isNotEmpty())
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            @foreach ($slot['rezervacije'] as $rez)
                                                <flux:badge size="sm" :color="$rez->je_trajna ? 'green' : 'amber'">
                                                    {{ __('Stan') }} {{ $rez->stan->broj }} · {{ $rez->broj_osoba }}
                                                </flux:badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </flux:card>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Termin') }}</flux:table.column>
            <flux:table.column>{{ __('Stan') }}</flux:table.column>
            <flux:table.column>{{ __('Vlasnik') }}</flux:table.column>
            <flux:table.column>{{ __('Osoba') }}</flux:table.column>
            <flux:table.column>{{ __('Stanje') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->rezervacije as $rez)
                <flux:table.row :key="$rez->id">
                    <flux:table.cell>{{ $this->termin($rez) }}</flux:table.cell>
                    <flux:table.cell variant="strong">{{ $rez->stan->broj }}</flux:table.cell>
                    <flux:table.cell>{{ $rez->vlasnik?->punoIme() }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $rez->evidentirano_osoba ?? $rez->broj_osoba }}/{{ $rez->broj_osoba }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-1">
                            <flux:badge size="sm" :color="$this->stanjeBoja($rez->stanje)">{{ $rez->stanje->label() }}</flux:badge>
                            @if ($this->jeAktivna($rez->stanje) && $rez->je_trajna)
                                <flux:badge size="sm" color="green">{{ __('Trajna') }}</flux:badge>
                            @elseif ($this->jeAktivna($rez->stanje))
                                <flux:badge size="sm" color="amber">{{ __('Uslovna') }}</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($this->jeAktivna($rez->stanje))
                            <flux:button size="sm" variant="danger"
                                wire:click="otkazi({{ $rez->id }})"
                                wire:confirm="{{ __('Otkazati ovu rezervaciju?') }}">
                                {{ __('Otkaži') }}
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text class="text-zinc-500">{{ __('Nema rezervacija za izabrani filter.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    @endif
</div>
