<?php

use App\Models\SpaConfig;
use App\Models\Zgrada;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Konfiguracija — upravnik')] class extends Component {
    public int $zgradaId = 0;

    public int $kapacitet = 25;

    public int $max_rez_7d = 4;

    public int $max_osoba = 5;

    public int $horizont_dana = 7;

    public string $radno_od = '12:00';

    public string $radno_do = '21:00';

    public int $broj_slotova = 3;

    public int $min_sati_pre = 2;

    public int $podsetnik_sati_pre = 3;

    public int $zakljucaj_sati_pre = 1;

    public bool $blokiraj_dug = false;

    public bool $aktivan = true;

    public function mount(): void
    {
        $this->zgradaId = (int) (Zgrada::whereHas('config')->value('id') ?? 0);
        $this->ucitaj();
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

    /**
     * Load the selected building's config into the form.
     */
    public function ucitaj(): void
    {
        $config = SpaConfig::where('zgrada_id', $this->zgradaId)->first();

        if ($config === null) {
            return;
        }

        $this->kapacitet = $config->kapacitet;
        $this->max_rez_7d = $config->max_rez_7d;
        $this->max_osoba = $config->max_osoba;
        $this->horizont_dana = $config->horizont_dana;
        $this->radno_od = substr($config->radno_od, 0, 5);
        $this->radno_do = substr($config->radno_do, 0, 5);
        $this->broj_slotova = $config->broj_slotova;
        $this->min_sati_pre = $config->min_sati_pre;
        $this->podsetnik_sati_pre = $config->podsetnik_sati_pre;
        $this->zakljucaj_sati_pre = $config->zakljucaj_sati_pre;
        $this->blokiraj_dug = $config->blokiraj_dug;
        $this->aktivan = $config->aktivan;
    }

    public function updatedZgradaId(): void
    {
        $this->ucitaj();
    }

    public function sacuvaj(): void
    {
        $validated = $this->validate([
            'kapacitet' => 'required|integer|min:1',
            'max_rez_7d' => 'required|integer|min:1',
            'max_osoba' => 'required|integer|min:1',
            'horizont_dana' => 'required|integer|min:1|max:60',
            'radno_od' => 'required|date_format:H:i',
            'radno_do' => 'required|date_format:H:i|after:radno_od',
            'broj_slotova' => 'required|integer|min:1|max:12',
            'min_sati_pre' => 'required|integer|min:0',
            'podsetnik_sati_pre' => 'required|integer|min:0',
            'zakljucaj_sati_pre' => 'required|integer|min:0',
            'blokiraj_dug' => 'boolean',
            'aktivan' => 'boolean',
        ]);

        $config = SpaConfig::where('zgrada_id', $this->zgradaId)->firstOrFail();
        $config->update($validated);

        Flux::toast(variant: 'success', text: __('Konfiguracija je sačuvana.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Konfiguracija spa centra') }}</flux:heading>

    <flux:card class="max-w-2xl">
        <form wire:submit="sacuvaj" class="flex flex-col gap-4">
            @if ($this->zgrade->count() > 1)
                <flux:select wire:model.live="zgradaId" :label="__('Zgrada')">
                    @foreach ($this->zgrade as $z)
                        <flux:select.option value="{{ $z->id }}">{{ $z->naziv }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:separator />
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input type="number" wire:model="kapacitet" :label="__('Kapacitet po terminu')" />
                <flux:input type="number" wire:model="max_osoba" :label="__('Max osoba po rezervaciji')" />
                <flux:input type="number" wire:model="max_rez_7d" :label="__('Max rezervacija po stanu (7 dana)')" />
                <flux:input type="number" wire:model="horizont_dana" :label="__('Horizont (dana unapred)')" />
                <flux:input type="time" wire:model="radno_od" :label="__('Radno vreme od')" />
                <flux:input type="time" wire:model="radno_do" :label="__('Radno vreme do')" />
                <flux:input type="number" wire:model="broj_slotova" :label="__('Broj slotova')" />
                <flux:input type="number" wire:model="min_sati_pre" :label="__('Min. sati pre termina')" />
                <flux:input type="number" wire:model="podsetnik_sati_pre" :label="__('Podsetnik (sati pre, 0 = isključeno)')" />
                <flux:input type="number" wire:model="zakljucaj_sati_pre" :label="__('Zaključavanje termina (sati pre)')" />
            </div>

            <div class="flex flex-col gap-3">
                <flux:checkbox wire:model="blokiraj_dug" :label="__('Blokiraj rezervaciju za stanove sa dugom')" />
                <flux:checkbox wire:model="aktivan" :label="__('Rezervacije aktivne')" />
            </div>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Sačuvaj') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
