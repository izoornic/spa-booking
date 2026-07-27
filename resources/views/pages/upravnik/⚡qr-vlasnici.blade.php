<?php

use App\Models\Vlasnik;
use App\Models\Zgrada;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('QR kodovi za vlasnike — upravnik')] class extends Component {
    public int $zgradaId = 0;

    public function mount(): void
    {
        $this->zgradaId = (int) (Zgrada::whereHas('config')->value('id') ?? 0);
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
    public function zgrada(): ?Zgrada
    {
        return Zgrada::find($this->zgradaId);
    }

    /**
     * Active owners of the selected building, ordered by apartment number.
     *
     * @return Collection<int, Vlasnik>
     */
    #[Computed]
    public function vlasnici(): Collection
    {
        if ($this->zgradaId === 0) {
            return collect();
        }

        return Vlasnik::query()
            ->where('aktivan', true)
            ->whereHas('stan', fn ($q) => $q->where('zgrada_id', $this->zgradaId))
            ->with('stan')
            ->get()
            ->sortBy(fn (Vlasnik $v): string => $v->stan->broj, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}; ?>

<div class="flex flex-col gap-6">
    <style>
        @media print {
            /* Odštampaj samo QR panel — sakrij sidebar, kontrole i ostalo. */
            body * { visibility: hidden !important; }
            #qr-print, #qr-print * { visibility: visible !important; }
            #qr-print { position: absolute; inset: 0; width: 100%; }
        }
    </style>

    <div class="flex items-center justify-between gap-3 print:hidden">
        <flux:heading size="xl">{{ __('QR kodovi za vlasnike') }}</flux:heading>
        @if ($this->vlasnici->isNotEmpty())
            <flux:button icon="printer" variant="primary" x-on:click="window.print()">
                {{ __('Štampaj') }}
            </flux:button>
        @endif
    </div>

    <flux:text class="print:hidden">
        {{ __('Svaki QR kod vodi na lični link vlasnika za pristup spa aplikaciji (bez lozinke). Odštampajte i podelite stanarima.') }}
    </flux:text>

    @if ($this->zgrade->count() > 1)
        <flux:select wire:model.live="zgradaId" :label="__('Zgrada')" class="max-w-sm print:hidden">
            @foreach ($this->zgrade as $z)
                <flux:select.option value="{{ $z->id }}">{{ $z->naziv }}</flux:select.option>
            @endforeach
        </flux:select>
    @endif

    @if ($this->zgrade->isEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" class="print:hidden">
            {{ __('Nijedna zgrada nema podešen spa. Prvo dodajte konfiguraciju.') }}
        </flux:callout>
    @elseif ($this->vlasnici->isEmpty())
        <flux:text class="text-zinc-500 print:hidden">{{ __('Nema aktivnih vlasnika za izabranu zgradu.') }}</flux:text>
    @else
        <flux:text class="text-sm text-zinc-500 print:hidden">
            {{ __('Ukupno vlasnika') }}: <strong>{{ $this->vlasnici->count() }}</strong>
        </flux:text>

        <div id="qr-print">
            <div class="mb-4 hidden print:block">
                <flux:heading size="lg">{{ $this->zgrada?->naziv }}</flux:heading>
                <flux:text class="text-sm">{{ __('QR kodovi za pristup spa aplikaciji') }}</flux:text>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 print:grid-cols-3">
                @foreach ($this->vlasnici as $v)
                    <div wire:key="qr-{{ $v->id }}"
                        class="flex flex-col items-center gap-2 rounded-lg border border-zinc-200 p-4 text-center dark:border-zinc-700 print:break-inside-avoid">
                        <div class="text-sm font-semibold">{{ __('Stan') }} {{ $v->stan->broj }}</div>
                        <canvas
                            x-data="qrCode(@js(route('spa.access', $v->token)), 150)"
                            x-init="$nextTick(() => render())"
                            x-ref="canvas"
                            class="rounded bg-white p-1"></canvas>
                        <div class="text-sm text-zinc-600 dark:text-zinc-300">{{ $v->punoIme() }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
