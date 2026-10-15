<?php

declare(strict_types=1);

namespace App\Models\VerificationCases;

use App\Enums\VerificationCases\VerificationCaseState;
use App\Models\Capsules\CapsuleVersion;
use App\Models\HelpRequest;
use App\Models\User;
use Database\Factories\VerificationCases\VerificationCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property VerificationCaseState $state
 * @property int $current_revision
 * @property int $lock_version
 */
#[Fillable([])]
class VerificationCase extends Model
{
    /** @use HasFactory<VerificationCaseFactory> */
    use HasFactory, HasUuids;

    /** @var array<string, string> */
    protected $casts = [
        'state' => VerificationCaseState::class,
        'current_revision' => 'integer',
        'lock_version' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<HelpRequest, $this> */
    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class, 'help_request_id');
    }

    /** @return BelongsTo<CapsuleVersion, $this> */
    public function capsuleVersion(): BelongsTo
    {
        return $this->belongsTo(CapsuleVersion::class, 'capsule_version_id');
    }

    /** @return HasMany<VerificationCaseRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(VerificationCaseRevision::class, 'case_id');
    }
}
