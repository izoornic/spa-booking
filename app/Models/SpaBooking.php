<?php

namespace App\Models;

use App\Enums\BookingState;
use Database\Factories\SpaBookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $zgrada_id
 * @property int $stan_id
 * @property int|null $vlasnik_id
 * @property Carbon $datum
 * @property int $slot_index
 * @property int $broj_osoba
 * @property BookingState $stanje
 * @property bool $je_trajna
 * @property string|null $qr_token
 * @property int|null $evidentirano_osoba
 * @property int|null $created_by
 */
#[Fillable([
    'zgrada_id', 'stan_id', 'vlasnik_id', 'datum', 'slot_index', 'broj_osoba',
    'stanje', 'je_trajna', 'qr_token', 'evidentirano_osoba', 'created_by',
])]
class SpaBooking extends Model
{
    /** @use HasFactory<SpaBookingFactory> */
    use HasFactory;

    protected $table = 'spa_booking';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'stanje' => BookingState::class,
            'je_trajna' => 'boolean',
        ];
    }

    /**
     * Only reservations that still occupy capacity.
     *
     * @param  Builder<SpaBooking>  $query
     */
    public function scopeAktivne(Builder $query): void
    {
        $query->whereIn('stanje', array_column(BookingState::active(), 'value'));
    }

    /**
     * Reservations in a specific slot of a building.
     *
     * @param  Builder<SpaBooking>  $query
     */
    public function scopeUSlotu(Builder $query, int $zgradaId, \DateTimeInterface $datum, int $slotIndex): void
    {
        $query->where('zgrada_id', $zgradaId)
            ->whereDate('datum', $datum)
            ->where('slot_index', $slotIndex);
    }

    /**
     * Reservations on or after a given day.
     *
     * @param  Builder<SpaBooking>  $query
     */
    public function scopeNadolazece(Builder $query, \DateTimeInterface $today): void
    {
        $query->whereDate('datum', '>=', $today);
    }

    /**
     * @return BelongsTo<Zgrada, $this>
     */
    public function zgrada(): BelongsTo
    {
        return $this->belongsTo(Zgrada::class);
    }

    /**
     * @return BelongsTo<Stan, $this>
     */
    public function stan(): BelongsTo
    {
        return $this->belongsTo(Stan::class);
    }

    /**
     * @return BelongsTo<Vlasnik, $this>
     */
    public function vlasnik(): BelongsTo
    {
        return $this->belongsTo(Vlasnik::class);
    }
}
