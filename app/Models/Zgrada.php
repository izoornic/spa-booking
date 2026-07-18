<?php

namespace App\Models;

use Database\Factories\ZgradaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $naziv
 * @property string|null $adresa
 */
#[Fillable(['naziv', 'adresa'])]
class Zgrada extends Model
{
    /** @use HasFactory<ZgradaFactory> */
    use HasFactory;

    protected $table = 'zgrada';

    /**
     * @return HasMany<Stan, $this>
     */
    public function stanovi(): HasMany
    {
        return $this->hasMany(Stan::class);
    }

    /**
     * @return HasOne<SpaConfig, $this>
     */
    public function config(): HasOne
    {
        return $this->hasOne(SpaConfig::class);
    }

    /**
     * @return HasMany<SpaBlokada, $this>
     */
    public function blokade(): HasMany
    {
        return $this->hasMany(SpaBlokada::class);
    }

    /**
     * @return HasMany<SpaBooking, $this>
     */
    public function rezervacije(): HasMany
    {
        return $this->hasMany(SpaBooking::class);
    }
}
