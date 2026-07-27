<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SpaConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $zgrada_id
 * @property int $kapacitet
 * @property int $max_rez_7d
 * @property int $max_osoba
 * @property int $horizont_dana
 * @property string $radno_od
 * @property string $radno_do
 * @property int $broj_slotova
 * @property int $min_sati_pre
 * @property int $podsetnik_sati_pre
 * @property int $zakljucaj_sati_pre
 * @property bool $blokiraj_dug
 * @property bool $aktivan
 */
#[Fillable([
    'zgrada_id', 'kapacitet', 'max_rez_7d', 'max_osoba', 'horizont_dana',
    'radno_od', 'radno_do', 'broj_slotova', 'min_sati_pre', 'podsetnik_sati_pre',
    'zakljucaj_sati_pre', 'blokiraj_dug', 'aktivan',
])]
class SpaConfig extends Model
{
    /** @use HasFactory<SpaConfigFactory> */
    use HasFactory;

    protected $table = 'spa_config';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blokiraj_dug' => 'boolean',
            'aktivan' => 'boolean',
        ];
    }

    /**
     * Compute the fixed daily slots from working hours and slot count.
     * Slots are equal-length blocks that evenly fill radno_od..radno_do.
     *
     * @return array<int, array{start: string, end: string}> keyed by slot_index (1-based)
     */
    public function slots(): array
    {
        $start = Carbon::parse($this->radno_od);
        $end = Carbon::parse($this->radno_do);
        $slotMinutes = intdiv((int) $start->diffInMinutes($end), max(1, $this->broj_slotova));

        $slots = [];
        for ($i = 1; $i <= $this->broj_slotova; $i++) {
            $slotStart = $start->copy()->addMinutes($slotMinutes * ($i - 1));
            $slotEnd = $slotStart->copy()->addMinutes($slotMinutes);
            $slots[$i] = [
                'start' => $slotStart->format('H:i'),
                'end' => $slotEnd->format('H:i'),
            ];
        }

        return $slots;
    }

    /**
     * Window (start/end "H:i") for a single slot index.
     *
     * @return array{start: string, end: string}
     */
    public function slotWindow(int $slotIndex): array
    {
        $slots = $this->slots();

        if (! isset($slots[$slotIndex])) {
            throw new \InvalidArgumentException("Nepostojeći slot: {$slotIndex}.");
        }

        return $slots[$slotIndex];
    }

    /**
     * Absolute start datetime of a slot on a given date.
     */
    public function slotStartAt(\DateTimeInterface $datum, int $slotIndex): CarbonImmutable
    {
        return $this->combine($datum, $this->slotWindow($slotIndex)['start']);
    }

    /**
     * Absolute end datetime of a slot on a given date.
     */
    public function slotEndAt(\DateTimeInterface $datum, int $slotIndex): CarbonImmutable
    {
        return $this->combine($datum, $this->slotWindow($slotIndex)['end']);
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
