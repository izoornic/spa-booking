<?php

namespace App\Models;

use Database\Factories\VlasnikFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $stan_id
 * @property string $ime
 * @property string $prezime
 * @property string|null $email
 * @property string|null $telefon
 * @property string $token
 * @property bool $aktivan
 */
#[Fillable(['stan_id', 'ime', 'prezime', 'email', 'telefon', 'aktivan'])]
#[Hidden(['token'])]
class Vlasnik extends Model
{
    /** @use HasFactory<VlasnikFactory> */
    use HasFactory;

    protected $table = 'vlasnik';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aktivan' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Vlasnik $vlasnik): void {
            if (empty($vlasnik->token)) {
                $vlasnik->token = static::generateToken();
            }
        });
    }

    /**
     * Generate a unique access token.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }

    /**
     * Issue a fresh access token, invalidating the owner's previous QR link
     * and any owner session started with it (see EnsureOwner).
     */
    public function regenerateToken(): string
    {
        $this->forceFill(['token' => static::generateToken()])->save();

        return $this->token;
    }

    /**
     * Full name of the owner.
     */
    public function punoIme(): string
    {
        return trim("{$this->ime} {$this->prezime}");
    }

    /**
     * @return BelongsTo<Stan, $this>
     */
    public function stan(): BelongsTo
    {
        return $this->belongsTo(Stan::class);
    }
}
