<?php

declare(strict_types=1);

namespace App\Models\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Models\User;
use Database\Factories\Capsules\CapsuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CapsuleVisibility $visibility
 * @property string|null $source_request_id
 * @property string|null $editorial_origin
 * @property string $owner_id
 * @property string $slug
 * @property string $id
 */
#[Fillable(['slug', 'editorial_origin'])]
class Capsule extends Model
{
    /** @use HasFactory<CapsuleFactory> */
    use HasFactory, HasUuids;

    /** @var array<string, string> */
    protected $casts = [
        'visibility' => CapsuleVisibility::class,
    ];

    /** @return Attribute<string, string> */
    protected function slug(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strtolower(trim($value)));
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<CapsuleVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CapsuleVersion::class);
    }

    /**
     * Dernière version publiée (B26) : point d'ancrage de la carte catalogue.
     *
     * @return HasOne<CapsuleVersion, $this>
     */
    public function latestPublished(): HasOne
    {
        return $this->hasOne(CapsuleVersion::class)->ofMany(['published_at' => 'max'], function ($query): void {
            $query->where('state', CapsuleVersionState::Published);
        });
    }
}
