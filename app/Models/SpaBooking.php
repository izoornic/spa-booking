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
use Illuminate\Support\Str;

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
 * @property string|null $kratki_kod
 * @property int|null $evidentirano_osoba
 * @property Carbon|null $podsetnik_poslat_at
 * @property int|null $created_by
 */
#[Fillable([
    'zgrada_id', 'stan_id', 'vlasnik_id', 'datum', 'slot_index', 'broj_osoba',
    'stanje', 'je_trajna', 'qr_token', 'kratki_kod', 'evidentirano_osoba',
    'podsetnik_poslat_at', 'created_by',
])]
class SpaBooking extends Model
{
    /** @use HasFactory<SpaBookingFactory> */
    use HasFactory;

    protected $table = 'spa_booking';

    /**
     * Assign a QR token and short code to every new reservation.
     */
    protected static function booted(): void
    {
        static::creating(function (SpaBooking $booking): void {
            $booking->qr_token ??= self::freshQrToken();
            $booking->kratki_kod ??= self::freshKratkiKod();
        });
    }

    /**
     * A globally unique 40-char token embedded in the reservation QR code.
     */
    public static function freshQrToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::where('qr_token', $token)->exists());

        return $token;
    }

    /**
     * A short, human-typeable code (unambiguous uppercase alphabet, no O/0/I/1).
     */
    public static function freshKratkiKod(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $kod = '';
            for ($i = 0; $i < 6; $i++) {
                $kod .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('kratki_kod', $kod)->exists());

        return $kod;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'stanje' => BookingState::class,
            'je_trajna' => 'boolean',
            'podsetnik_poslat_at' => 'datetime',
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
     * Match a reservation by either its QR token or its short code (case-insensitive).
     *
     * @param  Builder<SpaBooking>  $query
     */
    public function scopePoKodu(Builder $query, string $kod): void
    {
        $kod = trim($kod);

        $query->where('qr_token', $kod)
            ->orWhere('kratki_kod', strtoupper($kod));
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
