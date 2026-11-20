<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SpaConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $zgrada_id
 * @property int $kapacitet
 * @property int $max_rez_7d
 * @property int $max_osoba
 * @property int $horizont_dana
 * @property array<int|string, list<array{start: string, end: string}>>|null $raspored
 * @property int $min_sati_pre
 * @property int $podsetnik_sati_pre
 * @property int $zakljucaj_sati_pre
 * @property bool $blokiraj_dug
 * @property bool $aktivan
 */
#[Fillable([
    'zgrada_id', 'kapacitet', 'max_rez_7d', 'max_osoba', 'horizont_dana',
    'raspored', 'min_sati_pre', 'podsetnik_sati_pre',
    'zakljucaj_sati_pre', 'blokiraj_dug', 'aktivan',
])]
class SpaConfig extends Model
{
    /** @use HasFactory<SpaConfigFactory> */
    use HasFactory;

    /**
     * Day names keyed by ISO weekday (1 = Monday … 7 = Sunday).
     */
    public const array DANI = [
        1 => 'Ponedeljak',
        2 => 'Utorak',
        3 => 'Sreda',
        4 => 'Četvrtak',
        5 => 'Petak',
        6 => 'Subota',
        7 => 'Nedelja',
    ];

    protected $table = 'spa_config';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raspored' => 'array',
            'blokiraj_dug' => 'boolean',
            'aktivan' => 'boolean',
        ];
    }

    /**
     * The default weekly schedule: 12–15, 15–18, 18–21 every day.
     *
     * @return array<int, list<array{start: string, end: string}>>
     */
    public static function podrazumevaniRaspored(): array
    {
        $termini = [
            ['start' => '12:00', 'end' => '15:00'],
            ['start' => '15:00', 'end' => '18:00'],
            ['start' => '18:00', 'end' => '21:00'],
        ];

        return array_fill_keys(array_keys(self::DANI), $termini);
    }

    /**
     * The termini of one ISO weekday (1 = Monday … 7 = Sunday), ordered by start.
     * An empty array means the spa is closed that day.
     *
     * @return array<int, array{start: string, end: string}> keyed by slot_index (1-based)
     */
    public function slotsForWeekday(int $isoWeekday): array
    {
        $raspored = $this->raspored ?? [];
        $termini = $raspored[$isoWeekday] ?? $raspored[(string) $isoWeekday] ?? [];

        usort($termini, fn (array $a, array $b): int => strcmp($a['start'], $b['start']));

        $slots = [];
        foreach ($termini as $i => $termin) {
            $slots[$i + 1] = ['start' => $termin['start'], 'end' => $termin['end']];
        }

        return $slots;
    }

    /**
     * The weekly schedule with consecutive days of identical termini merged,
     * e.g. "Ponedeljak – Petak: 12:00–15:00, …" / "Svaki dan: …".
     *
     * @return list<array{dani: string, termini: list<string>}>
     */
    public function rasporedPoGrupama(): array
    {
        /** @var list<array{od: int, do: int, slots: array<int, array{start: string, end: string}>}> $grupe */
        $grupe = [];

        foreach (array_keys(self::DANI) as $dan) {
            $slots = $this->slotsForWeekday($dan);
            $poslednja = array_key_last($grupe);

            if ($poslednja !== null && $grupe[$poslednja]['slots'] === $slots) {
                $grupe[$poslednja]['do'] = $dan;
            } else {
                $grupe[] = ['od' => $dan, 'do' => $dan, 'slots' => $slots];
            }
        }

        return array_map(fn (array $grupa): array => [
            'dani' => match (true) {
                $grupa['od'] === 1 && $grupa['do'] === 7 => __('Svaki dan'),
                $grupa['od'] === $grupa['do'] => __(self::DANI[$grupa['od']]),
                default => __(self::DANI[$grupa['od']]).' – '.__(self::DANI[$grupa['do']]),
            },
            'termini' => array_values(array_map(
                fn (array $slot): string => $slot['start'].'–'.$slot['end'],
                $grupa['slots'],
            )),
        ], $grupe);
    }

    /**
     * The termini on a given date (depends on its day of the week).
     *
     * @return array<int, array{start: string, end: string}> keyed by slot_index (1-based)
     */
    public function slotsFor(\DateTimeInterface $datum): array
    {
        return $this->slotsForWeekday(CarbonImmutable::instance($datum)->dayOfWeekIso);
    }

    /**
     * Whether the slot index exists on the given date.
     */
    public function hasSlot(\DateTimeInterface $datum, int $slotIndex): bool
    {
        return isset($this->slotsFor($datum)[$slotIndex]);
    }

    /**
     * Window (start/end "H:i") for a single slot on a given date.
     *
     * @return array{start: string, end: string}
     */
    public function slotWindow(\DateTimeInterface $datum, int $slotIndex): array
    {
        $slots = $this->slotsFor($datum);

        if (! isset($slots[$slotIndex])) {
            throw new \InvalidArgumentException("Nepostojeći slot: {$slotIndex}.");
        }

        return $slots[$slotIndex];
    }

    /**
     * Display label "HH:MM–HH:MM" for a slot; falls back to "N. termin" for a slot that no
     * longer exists in the schedule (e.g. a past reservation made under an older schedule).
     */
    public function slotOpis(\DateTimeInterface $datum, int $slotIndex): string
    {
        $slots = $this->slotsFor($datum);

        if (! isset($slots[$slotIndex])) {
            return $slotIndex.'. '.__('termin');
        }

        return $slots[$slotIndex]['start'].'–'.$slots[$slotIndex]['end'];
    }

    /**
     * Absolute start datetime of a slot on a given date. A slot missing from the schedule
     * (only possible for past reservations made under an older schedule) resolves to the
     * start of that day, so it always reads as already finished.
     */
    public function slotStartAt(\DateTimeInterface $datum, int $slotIndex): CarbonImmutable
    {
        $slot = $this->slotsFor($datum)[$slotIndex] ?? null;

        return $slot === null
            ? CarbonImmutable::instance($datum)->startOfDay()
            : $this->combine($datum, $slot['start']);
    }

    /**
     * Absolute end datetime of a slot on a given date (see slotStartAt for missing slots).
     */
    public function slotEndAt(\DateTimeInterface $datum, int $slotIndex): CarbonImmutable
    {
        $slot = $this->slotsFor($datum)[$slotIndex] ?? null;

        return $slot === null
            ? CarbonImmutable::instance($datum)->startOfDay()
            : $this->combine($datum, $slot['end']);
    }

    /**
     * The moment a slot locks (zakljucaj_sati_pre hours before it starts). From this point
     * every reservation in the slot is guaranteed (trajna) and can no longer be displaced.
     */
    public function slotLockAt(\DateTimeInterface $datum, int $slotIndex): CarbonImmutable
    {
        return $this->slotStartAt($datum, $slotIndex)->subHours($this->zakljucaj_sati_pre);
    }

    /**
     * Whether the slot is currently within its locked window (locked and not yet ended).
     */
    public function slotZakljucan(\DateTimeInterface $datum, int $slotIndex, CarbonImmutable $now): bool
    {
        return $this->slotLockAt($datum, $slotIndex)->lte($now)
            && $this->slotEndAt($datum, $slotIndex)->gt($now);
    }

    /**
     * Merge a date with an "H:i" time into an immutable datetime.
     */
    private function combine(\DateTimeInterface $datum, string $time): CarbonImmutable
    {
        [$hour, $minute] = explode(':', $time);

        return CarbonImmutable::instance($datum)->setTime((int) $hour, (int) $minute);
    }

    /**
     * @return BelongsTo<Zgrada, $this>
     */
    public function zgrada(): BelongsTo
    {
        return $this->belongsTo(Zgrada::class);
    }
}
