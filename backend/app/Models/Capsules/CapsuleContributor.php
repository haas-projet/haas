<?php

declare(strict_types=1);

namespace App\Models\Capsules;

use App\Enums\Capsules\ContributionRole;
use App\Models\User;
use Database\Factories\Capsules\CapsuleContributorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ContributionRole $contribution_role
 */
#[Fillable([])]
class CapsuleContributor extends Model
{
    /** @use HasFactory<CapsuleContributorFactory> */
    use HasFactory, HasUuids;

    /** @var array<string, string> */
    protected $casts = [
        'contribution_role' => ContributionRole::class,
    ];

    /** @return BelongsTo<CapsuleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CapsuleVersion::class, 'version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
