<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $zgrada_id
 * @property Carbon $datum
 * @property int|null $slot_index
 * @property string|null $razlog
 * @property int|null $created_by
 */
#[Fillable(['zgrada_id', 'datum', 'slot_index', 'razlog', 'created_by'])]
class SpaBlokada extends Model
{
    protected $table = 'spa_blokada';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'datum' => 'date',
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
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
