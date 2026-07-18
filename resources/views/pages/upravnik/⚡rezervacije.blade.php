<?php

use App\Enums\BookingState;
use App\Exceptions\BookingException;
use App\Models\SpaBooking;
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

    public function otkazi(int $bookingId, SpaBookingService $service): void
    {
        $booking = SpaBooking::findOrFail($bookingId);

        try {
            $service->cancelAsManager($booking);
            Flux::toast(variant: 'success', text: __('Rezervacija je otkazana.'));
        } catch (BookingException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        unset($this->rezervacije);
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
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Rezervacije') }}</flux:heading>

        <flux:select wire:model.live="filter" class="max-w-48">
            <flux:select.option value="nadolazece">{{ __('Nadolazeće') }}</flux:select.option>
            <flux:select.option value="danas">{{ __('Danas') }}</flux:select.option>
            <flux:select.option value="sve">{{ __('Sve') }}</flux:select.option>
        </flux:select>
    </div>

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
</div>
