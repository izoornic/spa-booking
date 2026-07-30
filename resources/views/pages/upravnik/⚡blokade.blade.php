<?php

use App\Exceptions\BookingException;
use App\Models\SpaBlokada;
use App\Models\SpaConfig;
use App\Models\Zgrada;
use App\Services\SpaBookingService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Blokade termina — upravnik')] class extends Component {
    public int $zgradaId = 0;

    #[Validate('required|date')]
    public string $datum = '';

    /** Slot index, or 0 for the whole day. */
    #[Validate('required|integer|min:0')]
    public int $slot = 0;

    #[Validate('nullable|string|max:255')]
    public string $razlog = '';

    public function mount(): void
    {
        $this->zgradaId = (int) (Zgrada::whereHas('config')->value('id') ?? 0);
        $this->datum = CarbonImmutable::now()->addDay()->format('Y-m-d');
    }

    /**
     * Buildings that have a spa config.
     *
     * @return Collection<int, Zgrada>
     */
    #[Computed]
    public function zgrade(): Collection
    {
        return Zgrada::whereHas('config')->orderBy('naziv')->get();
    }

    #[Computed]
    public function config(): ?SpaConfig
    {
        return SpaConfig::where('zgrada_id', $this->zgradaId)->first();
    }

    /**
     * Slot options for the select: 0 = whole day, then each configured slot.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function slotOpcije(): array
    {
        $opcije = [0 => __('Ceo dan')];

        if ($this->config !== null) {
            foreach ($this->config->slots() as $index => $window) {
                $opcije[$index] = $window['start'].'–'.$window['end'];
            }
        }

        return $opcije;
    }

    /**
     * Upcoming blockades for the selected building.
     *
     * @return Collection<int, SpaBlokada>
     */
    #[Computed]
    public function blokade(): Collection
    {
        return SpaBlokada::where('zgrada_id', $this->zgradaId)
            ->whereDate('datum', '>=', CarbonImmutable::now()->startOfDay())
            ->orderBy('datum')
            ->orderBy('slot_index')
            ->get();
    }

    public function kreiraj(SpaBookingService $service): void
    {
        $this->validate();

        $zgrada = Zgrada::findOrFail($this->zgradaId);

        try {
            $service->blokiraj(
                $zgrada,
                CarbonImmutable::parse($this->datum),
                $this->slot === 0 ? null : $this->slot,
                $this->razlog !== '' ? $this->razlog : null,
                auth()->user(),
            );
        } catch (BookingException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->reset('razlog');
        unset($this->blokade);

        Flux::toast(variant: 'success', text: __('Termin je blokiran. Pogođene rezervacije su otkazane.'));
    }

    public function obrisi(int $blokadaId, SpaBookingService $service): void
    {
        $blokada = SpaBlokada::where('zgrada_id', $this->zgradaId)->findOrFail($blokadaId);

        $service->odblokiraj($blokada);
        unset($this->blokade);

        Flux::toast(variant: 'success', text: __('Blokada je uklonjena.'));
    }

    public function slotLabel(?int $slotIndex): string
    {
        if ($slotIndex === null) {
            return __('Ceo dan');
        }

        return $this->slotOpcije[$slotIndex] ?? (string) $slotIndex;
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Blokade termina') }}</flux:heading>

    {{-- Nova blokada --}}
    <flux:card>
        <form wire:submit="kreiraj" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Nova blokada') }}</flux:heading>

            @if ($this->zgrade->count() > 1)
                <flux:select wire:model.live="zgradaId" :label="__('Zgrada')">
                    @foreach ($this->zgrade as $z)
                        <flux:select.option value="{{ $z->id }}">{{ $z->naziv }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div class="grid gap-4 md:grid-cols-3">
                <flux:input type="date" wire:model="datum" :label="__('Datum')" />

                <flux:select wire:model="slot" :label="__('Termin')">
                    @foreach ($this->slotOpcije as $vrednost => $tekst)
                        <flux:select.option value="{{ $vrednost }}">{{ $tekst }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="razlog" :label="__('Razlog (opciono)')" placeholder="{{ __('npr. Održavanje') }}" />
            </div>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="lock-closed">{{ __('Blokiraj termin') }}</flux:button>
            </div>
        </form>
    </flux:card>

    {{-- Postojeće blokade --}}
    <div class="flex flex-col gap-2">
        <flux:heading size="lg">{{ __('Aktivne blokade') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Datum') }}</flux:table.column>
                <flux:table.column>{{ __('Termin') }}</flux:table.column>
                <flux:table.column>{{ __('Razlog') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->blokade as $b)
                    <flux:table.row :key="$b->id">
                        <flux:table.cell variant="strong">
                            {{ \Carbon\CarbonImmutable::instance($b->datum)->format('d.m.Y.') }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $this->slotLabel($b->slot_index) }}</flux:table.cell>
                        <flux:table.cell>{{ $b->razlog ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="ghost"
                                wire:click="obrisi({{ $b->id }})"
                                wire:confirm="{{ __('Ukloniti ovu blokadu?') }}">
                                {{ __('Ukloni') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <flux:text class="text-zinc-500">{{ __('Nema aktivnih blokada.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
