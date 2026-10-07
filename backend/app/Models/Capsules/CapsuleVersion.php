<?php

declare(strict_types=1);

namespace App\Models\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Models\User;
use Database\Factories\Capsules\CapsuleVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CapsuleVersionState $state
 * @property Carbon|null $published_at
 */
#[Fillable(['capsule_id', 'version_label', 'body', 'limits', 'state', 'reviewer_id', 'published_at'])]
class CapsuleVersion extends Model
{
    /** @use HasFactory<CapsuleVersionFactory> */
    use HasFactory, HasUuids;

    /** @var array<string, string> */
    protected $casts = [
        'state' => CapsuleVersionState::class,
        'published_at' => 'datetime',
    ];

    /** @return BelongsTo<Capsule, $this> */
    public function capsule(): BelongsTo
    {
        return $this->belongsTo(Capsule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
