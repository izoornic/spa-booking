<?php

use App\Enums\BookingState;
use App\Exceptions\BookingException;
use App\Models\SpaBooking;
use App\Models\SpaConfig;
use App\Services\SpaAttendantService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.staff')] #[Title('Domar — rezervacija')] class extends Component {
    public ?SpaBooking $booking = null;

    public ?SpaConfig $config = null;

    public int $prisutno = 0;

    public function mount(string $kod, SpaAttendantService $service): void
    {
        try {
            $booking = $service->findByCode($kod);
        } catch (BookingException) {
            return;
        }

        $booking->loadMissing('stan', 'zgrada.config', 'vlasnik');

        $this->booking = $booking;
        $this->config = $booking->zgrada?->config;
        $this->prisutno = $booking->evidentirano_osoba ?? $booking->broj_osoba;
    }

    /**
     * Whether the reservation is still in an evidenceable state (booked/confirmed).
     */
    public function jeAktivna(): bool
    {
        return $this->booking !== null
            && in_array($this->booking->stanje, [BookingState::Booked, BookingState::Confirmed], true);
    }

    /**
     * Whether the reservation's termin is today (evidencija is only allowed then).
     */
    public function jeDanas(): bool
    {
        return $this->booking !== null
            && CarbonImmutable::instance($this->booking->datum)->isSameDay(CarbonImmutable::now());
    }

    /**
     * Whether the attendant can evidence the reservation right now.
     */
    public function jeEvidentiv(): bool
    {
        return $this->jeAktivna() && $this->jeDanas();
    }

    public function potvrdi(SpaAttendantService $service): void
    {
        if ($this->booking === null) {
            return;
        }

        try {
            $this->booking = $service->confirm($this->booking, $this->prisutno);
            Flux::toast(variant: 'success', text: __('Dolazak je evidentiran.'));
        } catch (BookingException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function nijeSePojavio(SpaAttendantService $service): void
    {
        if ($this->booking === null) {
            return;
        }

        try {
            $this->booking = $service->noShow($this->booking);
            Flux::toast(variant: 'warning', text: __('Označeno kao nedolazak.'));
        } catch (BookingException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function terminLabel(): string
    {
        if ($this->booking === null || $this->config === null) {
            return '';
        }

        $window = $this->config->slotWindow($this->booking->slot_index);
        $datum = CarbonImmutable::instance($this->booking->datum);

        return $datum->format('d.m.Y').' · '.$window['start'].'–'.$window['end'];
    }

    public function stanjeBadge(): string
    {
        return match ($this->booking?->stanje) {
            BookingState::Confirmed => 'green',
            BookingState::Booked => 'blue',
            BookingState::NoShow => 'red',
            default => 'zinc',
        };
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:button href="{{ route('domar.home') }}" wire:navigate variant="ghost" size="sm" icon="arrow-left" class="self-start">
        {{ __('Nazad na pregled') }}
    </flux:button>

    @if ($booking === null)
        <flux:callout variant="danger" icon="x-circle">
            <flux:callout.heading>{{ __('Rezervacija nije pronađena') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Proverite kod ili zamolite gosta da ponovo prikaže QR.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:card class="flex flex-col gap-4">
            <div class="flex items-start justify-between gap-2">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Stan') }} {{ $booking->stan->broj }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500">{{ $booking->vlasnik?->punoIme() }}</flux:text>
                </div>
                <flux:badge :color="$this->stanjeBadge()">{{ $booking->stanje->label() }}</flux:badge>
            </div>

            <flux:separator />

            <div class="flex flex-col gap-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-zinc-500">{{ __('Termin') }}</span>
                    <span class="font-medium">{{ $this->terminLabel() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500">{{ __('Rezervisano osoba') }}</span>
                    <span class="font-medium">{{ $booking->broj_osoba }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500">{{ __('Tip') }}</span>
                    <span>
                        @if ($booking->je_trajna)
                            <flux:badge size="sm" color="green">{{ __('Trajna') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">{{ __('Uslovna') }}</flux:badge>
                        @endif
                    </span>
                </div>
                @if ($booking->evidentirano_osoba !== null)
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Evidentirano prisutnih') }}</span>
                        <span class="font-medium">{{ $booking->evidentirano_osoba }}</span>
                    </div>
                @endif
            </div>
        </flux:card>

        @if ($this->jeEvidentiv())
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="sm">{{ __('Evidencija dolaska') }}</flux:heading>

                <flux:select wire:model="prisutno" :label="__('Broj prisutnih osoba')">
                    @for ($i = 0; $i <= $booking->broj_osoba; $i++)
                        <flux:select.option value="{{ $i }}">{{ $i }}</flux:select.option>
                    @endfor
                </flux:select>

                <div class="flex flex-col gap-2">
                    <flux:button wire:click="potvrdi" variant="primary" icon="check">
                        {{ __('Potvrdi dolazak') }}
                    </flux:button>
                    <flux:button wire:click="nijeSePojavio" variant="danger" icon="x-mark"
                        wire:confirm="{{ __('Označiti kao nedolazak?') }}">
                        {{ __('Nije se pojavio') }}
                    </flux:button>
                </div>
            </flux:card>
        @elseif ($this->jeAktivna() && ! $this->jeDanas())
            <flux:callout variant="secondary" icon="calendar-days">
                <flux:callout.text>{{ __('Rezervacija se može evidentirati samo na dan termina.') }}</flux:callout.text>
            </flux:callout>
        @else
            <flux:callout variant="secondary" icon="information-circle">
                <flux:callout.text>{{ __('Ova rezervacija se više ne može evidentirati.') }}</flux:callout.text>
            </flux:callout>
        @endif
    @endif
</div>
