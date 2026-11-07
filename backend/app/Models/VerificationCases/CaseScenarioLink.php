<?php

declare(strict_types=1);

namespace App\Models\VerificationCases;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $scenario_key
 * @property string $suite_digest
 * @property string $release_commit
 * @property Carbon $integrated_at
 */
#[Fillable([])]
class CaseScenarioLink extends Model
{
    use HasUuids;

    protected $table = 'case_scenario_links';

    /** @var array<string, string> */
    protected $casts = [
        'integrated_at' => 'datetime',
    ];

    /** @return BelongsTo<VerificationCaseRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(VerificationCaseRevision::class, 'case_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
