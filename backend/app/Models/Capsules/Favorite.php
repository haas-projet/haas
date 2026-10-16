<?php

declare(strict_types=1);

namespace App\Models\Capsules;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * B28 — Favori privé par (membre, capsule). Jamais listé par une
 * Resource publique (CAHIER_DES_CHARGES.md:506).
 */
#[Fillable([])]
class Favorite extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Capsule, $this> */
    public function capsule(): BelongsTo
    {
        return $this->belongsTo(Capsule::class);
    }
}
