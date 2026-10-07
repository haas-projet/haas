<?php

declare(strict_types=1);

namespace App\Models\Capsules;

use App\Enums\Capsules\ReviewDecision;
use App\Models\User;
use Database\Factories\Capsules\CapsuleVersionReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ReviewDecision $decision
 */
#[Fillable(['version_id', 'reviewer_id', 'decision', 'note'])]
class CapsuleVersionReview extends Model
{
    /** @use HasFactory<CapsuleVersionReviewFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $casts = [
        'decision' => ReviewDecision::class,
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<CapsuleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CapsuleVersion::class, 'version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
