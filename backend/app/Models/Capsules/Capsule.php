<?php

declare(strict_types=1);

namespace App\Models\Capsules;

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

/**
 * @property CapsuleVisibility $visibility
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
}
