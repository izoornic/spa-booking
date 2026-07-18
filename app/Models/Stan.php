<?php

namespace App\Models;

use Database\Factories\StanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $zgrada_id
 * @property string $broj
 * @property string|null $sprat
 * @property bool $ima_dug
 */
#[Fillable(['zgrada_id', 'broj', 'sprat', 'ima_dug'])]
class Stan extends Model
{
    /** @use HasFactory<StanFactory> */
    use HasFactory;

    protected $table = 'stan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ima_dug' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Zgrada, $this>
     */
    public function zgrada(): BelongsTo
    {
        return $this->belongsTo(Zgrada::class);
    }

    /**
     * @return HasMany<Vlasnik, $this>
     */
    public function vlasnici(): HasMany
    {
        return $this->hasMany(Vlasnik::class);
    }

    /**
     * @return HasMany<SpaBooking, $this>
     */
    public function rezervacije(): HasMany
    {
        return $this->hasMany(SpaBooking::class);
    }
}
