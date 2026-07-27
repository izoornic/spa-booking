<?php

use App\Enums\BookingState;
use App\Models\SpaBooking;
use App\Models\SpaConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.staff')] #[Title('Domar — pregled')] class extends Component {
    #[Validate('required|string|min:4|max:64')]
    public string $kod = '';

    /**
     * Look up a reservation by its scanned QR token or typed short code.
     */
    public function pretrazi(): void
    {
        $this->validate();

        $this->redirectRoute('domar.rezervacija', ['kod' => trim($this->kod)], navigate: true);
    }

    /**
     * Occupancy for today and tomorrow, grouped by building → day → slot.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function pregled(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $tomorrow = $today->addDay();
        $dani = [$today, $tomorrow];

        $zgrade = [];

        foreach (SpaConfig::with('zgrada')->get() as $config) {
            /** @var Collection<string, Collection<int, SpaBooking>> $bookings */
            $bookings = SpaBooking::aktivne()
                ->where('zgrada_id', $config->zgrada_id)
                ->whereBetween('datum', [$today, $tomorrow])
                ->with('stan')
                ->get()
                ->groupBy(fn (SpaBooking $b): string => $b->datum->format('Y-m-d').'#'.$b->slot_index);

            $daniData = [];

            foreach ($dani as $dan) {
                $dayKey = $dan->format('Y-m-d');
                $slotovi = [];

                for ($s = 1; $s <= $config->broj_slotova; $s++) {
                    $window = $config->slotWindow($s);
                    $inSlot = $bookings->get($dayKey.'#'.$s, collect())
                        ->sortByDesc(fn (SpaBooking $b): bool => $b->je_trajna)
                        ->values();

                    $slotovi[] = [
                        'index' => $s,
                        'label' => $window['start'].'–'.$window['end'],
                        'zauzeto' => (int) $inSlot->sum('broj_osoba'),
                        'kapacitet' => $config->kapacitet,
                        'rezervacije' => $inSlot,
                    ];
                }

                $daniData[] = ['datum' => $dan, 'slotovi' => $slotovi];
            }

            $zgrade[] = [
                'naziv' => $config->zgrada?->naziv ?? __('Spa'),
                'dani' => $daniData,
            ];
        }

        return $zgrade;
    }

    public function formatDatum(CarbonImmutable $datum): string
    {
        $today = CarbonImmutable::now()->startOfDay();

        if ($datum->isSameDay($today)) {
            return __('Danas');
        }

        if ($datum->isSameDay($today->addDay())) {
            return __('Sutra');
        }

        return $datum->format('d.m.Y');
    }

    public function stanjeBadge(BookingState $stanje): string
    {
        return match ($stanje) {
            BookingState::Confirmed => 'green',
            BookingState::Booked => 'blue',
            default => 'zinc',
        };
    }
}; ?>

<div class="flex flex-col gap-6">
    {{-- Unos koda / skeniranje --}}
    <flux:card class="flex flex-col gap-3" x-data="qrScanner" x-on:beforeunload.window="stop()">
        <flux:heading size="lg">{{ __('Evidencija dolaska') }}</flux:heading>
        <flux:text class="text-sm text-zinc-500">
            {{ __('Skenirajte QR ili unesite kratki kod sa rezervacije gosta.') }}
        </flux:text>

        {{-- Skeniranje kamerom --}}
        <div class="flex flex-col gap-2">
            <flux:button variant="primary" icon="qr-code" x-show="!active" x-on:click="start()">
                {{ __('Skeniraj QR kamerom') }}
            </flux:button>

            <div x-show="active" x-cloak class="flex flex-col gap-2">
                <div id="qr-reader" x-ref="reader" class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700"></div>
                <flux:button variant="ghost" icon="x-mark" x-on:click="stop()">
                    {{ __('Zaustavi skeniranje') }}
                </flux:button>
            </div>

            <flux:text x-show="error" x-cloak class="text-sm text-red-600 dark:text-red-400" x-text="error"></flux:text>
        </div>

        <flux:separator :text="__('ili')" />

        {{-- Ručni unos kratkog koda --}}
        <form wire:submit="pretrazi" class="flex items-end gap-2">
            <flux:input
                wire:model="kod"
                :label="__('Kod rezervacije')"
                placeholder="npr. ABC123"
                class="flex-1"
            />
            <flux:button type="submit" variant="primary" icon="magnifying-glass">
                {{ __('Nađi') }}
            </flux:button>
        </form>
    </flux:card>

    {{-- Pregled zauzeća --}}
    @foreach ($this->pregled as $zgrada)
        <div class="flex flex-col gap-3">
            <flux:heading size="lg">{{ $zgrada['naziv'] }}</flux:heading>

            @foreach ($zgrada['dani'] as $dan)
                <flux:card class="flex flex-col gap-3">
                    <flux:heading size="sm">{{ $this->formatDatum($dan['datum']) }}</flux:heading>

                    @foreach ($dan['slotovi'] as $slot)
                        <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="flex items-center justify-between">
                                <flux:text class="font-medium">{{ $slot['label'] }}</flux:text>
                                <flux:badge size="sm" :color="$slot['zauzeto'] >= $slot['kapacitet'] ? 'red' : 'zinc'">
                                    {{ $slot['zauzeto'] }}/{{ $slot['kapacitet'] }} {{ __('osoba') }}
                                </flux:badge>
                            </div>

                            @forelse ($slot['rezervacije'] as $rez)
                                <a href="{{ route('domar.rezervacija', ['kod' => $rez->kratki_kod ?? $rez->qr_token ?? $rez->id]) }}" wire:navigate
                                    class="flex items-center justify-between gap-2 rounded-md bg-zinc-50 px-3 py-2 text-sm hover:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                                    <span class="flex items-center gap-2">
                                        <strong>{{ __('Stan') }} {{ $rez->stan->broj }}</strong>
                                        @if ($rez->je_trajna)
                                            <flux:badge size="sm" color="green">{{ __('Trajna') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="amber">{{ __('Uslovna') }}</flux:badge>
                                        @endif
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <flux:badge size="sm" :color="$this->stanjeBadge($rez->stanje)">
                                            {{ $rez->stanje->label() }}
                                        </flux:badge>
                                        <span class="text-zinc-500">
                                            {{ $rez->evidentirano_osoba ?? $rez->broj_osoba }}/{{ $rez->broj_osoba }}
                                        </span>
                                    </span>
                                </a>
                            @empty
                                <flux:text class="text-sm text-zinc-400">{{ __('Nema rezervacija.') }}</flux:text>
                            @endforelse
                        </div>
                    @endforeach
                </flux:card>
            @endforeach
        </div>
    @endforeach
</div>
