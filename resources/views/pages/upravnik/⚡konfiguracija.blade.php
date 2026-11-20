<?php

use App\Exceptions\BookingException;
use App\Models\SpaConfig;
use App\Models\Zgrada;
use App\Services\SpaBookingService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Konfiguracija — upravnik')] class extends Component {
    /** Maximum number of termini on a single day. */
    private const int MAX_TERMINA = 12;

    public int $zgradaId = 0;

    public int $kapacitet = 25;

    public int $max_rez_7d = 4;

    public int $max_osoba = 5;

    public int $horizont_dana = 7;

    /**
     * Weekly schedule: ISO weekday (1 = ponedeljak … 7 = nedelja) → list of termini.
     *
     * @var array<int, list<array{start: string, end: string}>>
     */
    public array $raspored = [];

    public int $min_sati_pre = 2;

    public int $podsetnik_sati_pre = 3;

    public int $zakljucaj_sati_pre = 1;

    public bool $blokiraj_dug = false;

    public bool $aktivan = true;

    public function mount(): void
    {
        $this->zgradaId = (int) (Zgrada::whereHas('config')->value('id') ?? 0);
        $this->raspored = SpaConfig::podrazumevaniRaspored();
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
        $this->raspored = [];
        foreach (array_keys(SpaConfig::DANI) as $dan) {
            $this->raspored[$dan] = array_values($config->slotsForWeekday($dan));
        }
        $this->min_sati_pre = $config->min_sati_pre;
        $this->podsetnik_sati_pre = $config->podsetnik_sati_pre;
        $this->zakljucaj_sati_pre = $config->zakljucaj_sati_pre;
        $this->blokiraj_dug = $config->blokiraj_dug;
        $this->aktivan = $config->aktivan;
        $this->resetValidation();
    }

    public function updatedZgradaId(): void
    {
        $this->ucitaj();
    }

    /**
     * Append a termin to a day, starting where the day's last termin ends.
     */
    public function dodajTermin(int $dan): void
    {
        $termini = $this->raspored[$dan] ?? [];

        if (! isset(SpaConfig::DANI[$dan]) || count($termini) >= self::MAX_TERMINA) {
            return;
        }

        $poslednji = end($termini);
        $start = $poslednji === false ? '12:00' : $poslednji['end'];
        [$sat, $minut] = array_map('intval', explode(':', $start)) + [0, 0];
        $end = sprintf('%02d:%02d', min(23, $sat + 3), $sat + 3 > 23 ? 59 : $minut);

        $termini[] = ['start' => $start, 'end' => $end];
        $this->raspored[$dan] = $termini;
    }

    public function ukloniTermin(int $dan, int $index): void
    {
        $termini = $this->raspored[$dan] ?? [];
        unset($termini[$index]);
        $this->raspored[$dan] = array_values($termini);
        $this->resetValidation();
    }

    /**
     * Copy one day's termini to every other day of the week.
     */
    public function kopirajNaSveDane(int $dan): void
    {
        if (! isset(SpaConfig::DANI[$dan])) {
            return;
        }

        $termini = array_values($this->raspored[$dan] ?? []);

        foreach (array_keys(SpaConfig::DANI) as $d) {
            $this->raspored[$d] = $termini;
        }

        $this->resetValidation();
    }

    public function sacuvaj(SpaBookingService $service): void
    {
        $validated = $this->validate([
            'kapacitet' => 'required|integer|min:1',
            'max_rez_7d' => 'required|integer|min:1',
            'max_osoba' => 'required|integer|min:1',
            'horizont_dana' => 'required|integer|min:1|max:60',
            'raspored' => 'array',
            'raspored.*' => 'array|max:'.self::MAX_TERMINA,
            'raspored.*.*.start' => 'required|date_format:H:i',
            'raspored.*.*.end' => 'required|date_format:H:i',
            'min_sati_pre' => 'required|integer|min:0',
            'podsetnik_sati_pre' => 'required|integer|min:0',
            'zakljucaj_sati_pre' => 'required|integer|min:0',
            'blokiraj_dug' => 'boolean',
            'aktivan' => 'boolean',
        ], attributes: [
            'raspored.*.*.start' => __('početak'),
            'raspored.*.*.end' => __('kraj'),
        ]);

        $raspored = $this->normalizovanRaspored();

        if ($raspored === null) {
            return;
        }

        $config = SpaConfig::where('zgrada_id', $this->zgradaId)->firstOrFail();

        try {
            $service->promeniRaspored($config, $raspored);
        } catch (BookingException $e) {
            $this->addError('raspored', $e->getMessage());

            return;
        }

        unset($validated['raspored']);
        $config->update($validated);
        $this->ucitaj();

        Flux::toast(variant: 'success', text: __('Konfiguracija je sačuvana.'));
    }

    /**
     * The schedule sorted by start time per day, or null (with errors added) when a
     * termin ends before it starts or two termini of the same day overlap.
     *
     * @return array<int, list<array{start: string, end: string}>>|null
     */
    private function normalizovanRaspored(): ?array
    {
        $raspored = [];
        $ispravan = true;

        foreach (array_keys(SpaConfig::DANI) as $dan) {
            $termini = [];

            foreach ($this->raspored[$dan] ?? [] as $i => $termin) {
                if ($termin['end'] <= $termin['start']) {
                    $this->addError("raspored.$dan.$i.end", __('Kraj termina mora biti posle početka.'));
                    $ispravan = false;
                }

                $termini[$i] = ['start' => $termin['start'], 'end' => $termin['end']];
            }

            uasort($termini, fn (array $a, array $b): int => strcmp($a['start'], $b['start']));

            $prethodni = null;
            foreach ($termini as $i => $termin) {
                if ($prethodni !== null && $termin['start'] < $prethodni['end']) {
                    $this->addError("raspored.$dan.$i.start", __('Termin se preklapa sa terminom :od–:do.', [
                        'od' => $prethodni['start'],
                        'do' => $prethodni['end'],
                    ]));
                    $ispravan = false;
                }
                $prethodni = $termin;
            }

            $raspored[$dan] = array_values($termini);
        }

        return $ispravan ? $raspored : null;
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Konfiguracija spa centra') }}</flux:heading>

    <form wire:submit="sacuvaj" class="flex flex-col gap-6">
        <flux:card class="max-w-2xl">
            <div class="flex flex-col gap-4">
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
                    <flux:input type="number" wire:model="min_sati_pre" :label="__('Min. sati pre termina')" />
                    <flux:input type="number" wire:model="podsetnik_sati_pre" :label="__('Podsetnik (sati pre, 0 = isključeno)')" />
                    <flux:input type="number" wire:model="zakljucaj_sati_pre" :label="__('Zaključavanje termina (sati pre)')" />
                </div>

                <div class="flex flex-col gap-3">
                    <flux:checkbox wire:model="blokiraj_dug" :label="__('Blokiraj rezervaciju za stanove sa dugom')" />
                    <flux:checkbox wire:model="aktivan" :label="__('Rezervacije aktivne')" />
                </div>
            </div>
        </flux:card>

        <div class="flex max-w-2xl flex-col gap-3">
            <div>
                <flux:heading size="lg">{{ __('Raspored termina po danima') }}</flux:heading>
                <flux:text class="text-sm">
                    {{ __('Svaki dan u nedelji može imati svoje termine. Dan bez termina je zatvoren.') }}
                </flux:text>
            </div>

            <flux:error name="raspored" />

            @foreach (\App\Models\SpaConfig::DANI as $dan => $naziv)
                <flux:card class="!p-4 flex flex-col gap-3" wire:key="dan-{{ $dan }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <flux:heading size="md">{{ __($naziv) }}</flux:heading>
                            @if (($raspored[$dan] ?? []) === [])
                                <flux:badge size="sm" color="zinc">{{ __('Zatvoreno') }}</flux:badge>
                            @endif
                        </div>

                        <div class="flex gap-1">
                            <flux:button size="sm" variant="ghost" icon="document-duplicate"
                                wire:click="kopirajNaSveDane({{ $dan }})"
                                wire:confirm="{{ __('Kopirati termine ovog dana na sve dane u nedelji?') }}">
                                {{ __('Kopiraj na sve dane') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="plus" wire:click="dodajTermin({{ $dan }})">
                                {{ __('Dodaj termin') }}
                            </flux:button>
                        </div>
                    </div>

                    @foreach ($raspored[$dan] ?? [] as $i => $termin)
                        <div class="flex items-start gap-2" wire:key="dan-{{ $dan }}-termin-{{ $i }}">
                            <flux:text class="mt-2 w-6 shrink-0 font-medium">{{ $i + 1 }}.</flux:text>
                            <flux:input type="time" wire:model="raspored.{{ $dan }}.{{ $i }}.start" :aria-label="__('Početak')" />
                            <flux:text class="mt-2">–</flux:text>
                            <flux:input type="time" wire:model="raspored.{{ $dan }}.{{ $i }}.end" :aria-label="__('Kraj')" />
                            <flux:button size="sm" variant="ghost" icon="trash" class="mt-1"
                                wire:click="ukloniTermin({{ $dan }}, {{ $i }})"
                                :aria-label="__('Ukloni termin')" />
                        </div>
                    @endforeach
                </flux:card>
            @endforeach
        </div>

        <div class="flex max-w-2xl justify-end">
            <flux:button type="submit" variant="primary">{{ __('Sačuvaj') }}</flux:button>
        </div>
    </form>
</div>
