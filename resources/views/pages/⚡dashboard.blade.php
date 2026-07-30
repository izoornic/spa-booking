<?php

use App\Models\SpaBlokada;
use App\Models\SpaBooking;
use App\Models\SpaConfig;
use App\Models\Vlasnik;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Početna')] class extends Component {
    /**
     * Active reservations for today, across every building.
     *
     * @return Collection<int, SpaBooking>
     */
    #[Computed]
    public function danasnje(): Collection
    {
        return SpaBooking::aktivne()
            ->whereDate('datum', CarbonImmutable::now()->startOfDay())
            ->get();
    }

    /**
     * Headline numbers for the stat cards.
     *
     * @return array{rezervacija: int, osoba: int, vlasnika: int}
     */
    #[Computed]
    public function statistika(): array
    {
        return [
            'rezervacija' => $this->danasnje->count(),
            'osoba' => (int) $this->danasnje->sum('broj_osoba'),
            'vlasnika' => Vlasnik::where('aktivan', true)->count(),
        ];
    }

    /**
     * Today's slots per building with their occupancy and blockade state.
     *
     * @return array<int, array{naziv: string, slotovi: array<int, array<string, mixed>>}>
     */
    #[Computed]
    public function danas(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $zgrade = [];

        foreach (SpaConfig::with('zgrada')->get() as $config) {
            $blokade = SpaBlokada::where('zgrada_id', $config->zgrada_id)
                ->whereDate('datum', $today)
                ->get();

            $slotovi = [];

            for ($s = 1; $s <= $config->broj_slotova; $s++) {
                $window = $config->slotWindow($s);
                $uSlotu = $this->danasnje
                    ->where('zgrada_id', $config->zgrada_id)
                    ->where('slot_index', $s);

                $slotovi[] = [
                    'label' => $window['start'].'–'.$window['end'],
                    'zauzeto' => (int) $uSlotu->sum('broj_osoba'),
                    'kapacitet' => $config->kapacitet,
                    'blokiran' => $blokade->contains(
                        fn (SpaBlokada $b): bool => $b->slot_index === null || $b->slot_index === $s
                    ),
                ];
            }

            $zgrade[] = [
                'naziv' => $config->zgrada?->naziv ?? __('Spa'),
                'slotovi' => $slotovi,
            ];
        }

        return $zgrade;
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <flux:heading size="xl">{{ __('Zdravo') }}, {{ auth()->user()->name }}!</flux:heading>

    @if (auth()->user()->isManager())
        {{-- Brojke za danas --}}
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['label' => __('Rezervacija danas'), 'value' => $this->statistika['rezervacija'], 'icon' => 'calendar-days'],
                ['label' => __('Očekivano osoba'), 'value' => $this->statistika['osoba'], 'icon' => 'users'],
                ['label' => __('Aktivnih vlasnika'), 'value' => $this->statistika['vlasnika'], 'icon' => 'identification'],
            ] as $kartica)
                <flux:card class="flex items-center gap-4">
                    <flux:icon :icon="$kartica['icon']" class="size-8 text-sky-600 dark:text-sky-400" />
                    <div class="flex flex-col">
                        <flux:heading size="xl">{{ $kartica['value'] }}</flux:heading>
                        <flux:text class="text-sm">{{ $kartica['label'] }}</flux:text>
                    </div>
                </flux:card>
            @endforeach
        </div>

        {{-- Današnji termini --}}
        @foreach ($this->danas as $zgrada)
            <flux:card class="flex flex-col gap-3">
                <flux:heading size="lg">{{ __('Danas') }} — {{ $zgrada['naziv'] }}</flux:heading>

                @foreach ($zgrada['slotovi'] as $slot)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                        <flux:text class="font-medium">{{ $slot['label'] }}</flux:text>

                        @if ($slot['blokiran'])
                            <flux:badge size="sm" color="zinc">{{ __('Blokiran') }}</flux:badge>
                        @else
                            <flux:badge size="sm" :color="$slot['zauzeto'] >= $slot['kapacitet'] ? 'red' : 'zinc'">
                                {{ $slot['zauzeto'] }}/{{ $slot['kapacitet'] }} {{ __('osoba') }}
                            </flux:badge>
                        @endif
                    </div>
                @endforeach
            </flux:card>
        @endforeach

        {{-- Prečice --}}
        <div class="flex flex-wrap gap-2">
            <flux:button icon="calendar" :href="route('upravnik.rezervacije')" wire:navigate>
                {{ __('Rezervacije') }}
            </flux:button>
            <flux:button icon="lock-closed" :href="route('upravnik.blokade')" wire:navigate>
                {{ __('Blokade termina') }}
            </flux:button>
            <flux:button icon="qr-code" :href="route('upravnik.qr-vlasnici')" wire:navigate>
                {{ __('QR kodovi za vlasnike') }}
            </flux:button>
            <flux:button icon="cog-6-tooth" :href="route('upravnik.konfiguracija')" wire:navigate>
                {{ __('Konfiguracija') }}
            </flux:button>
        </div>
    @elseif (auth()->user()->isAttendant())
        <flux:card class="flex flex-col items-start gap-3">
            <flux:text>{{ __('Evidencija dolazaka i pregled zauzeća su na stranici domara.') }}</flux:text>
            <flux:button icon="qr-code" variant="primary" :href="route('domar.home')" wire:navigate>
                {{ __('Otvori pregled domara') }}
            </flux:button>
        </flux:card>
    @else
        <flux:card>
            <flux:text>{{ __('Nemate dodeljenu ulogu u spa aplikaciji. Obratite se upravniku zgrade.') }}</flux:text>
        </flux:card>
    @endif
</div>
