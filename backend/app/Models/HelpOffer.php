<?php

namespace App\Models;

use App\Enums\HelpOffers\HelpContributionCategory;
use App\Enums\HelpOffers\HelpOfferState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property HelpOfferState $state
 * @property HelpContributionCategory $category
 * @property int $lock_version
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $allow_public_summary_at
 */
#[Fillable(['category', 'public_summary', 'expected_outcome', 'allow_public_summary'])]
class HelpOffer extends Model
{
    use HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = ['state' => 'pending', 'lock_version' => 1, 'allow_public_summary' => false];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /** @return BelongsTo<HelpRequest, $this> */
    public function acceptedRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class, 'accepted_request_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['state' => HelpOfferState::class, 'category' => HelpContributionCategory::class,
            'lock_version' => 'integer', 'allow_public_summary' => 'boolean',
            'allow_public_summary_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }
}
