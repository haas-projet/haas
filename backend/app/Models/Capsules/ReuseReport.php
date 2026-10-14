<?php

declare(strict_types=1);

namespace App\Models\Capsules;

use App\Enums\Capsules\ReuseReportOutcome;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ReuseReportOutcome $outcome
 * @property bool $is_internal
 * @property int $revision
 */
#[Fillable([])]
class ReuseReport extends Model
{
    use HasUuids;

    protected $casts = [
        'outcome' => ReuseReportOutcome::class,
        'is_internal' => 'boolean',
        'revision' => 'integer',
        'reported_at' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CapsuleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CapsuleVersion::class, 'version_id');
    }

    /** @return HasMany<ReuseReportRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ReuseReportRevision::class, 'report_id');
    }
}
