<?php

declare(strict_types=1);

namespace App\Models\Capsules;

use App\Enums\Capsules\ReuseReportOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ReuseReportOutcome $outcome
 * @property bool $is_internal
 * @property int $revision
 */
#[Fillable([])]
class ReuseReportRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $casts = [
        'outcome' => ReuseReportOutcome::class,
        'is_internal' => 'boolean',
        'revision' => 'integer',
        'reported_at' => 'date',
        'revised_at' => 'datetime',
    ];

    /** @return BelongsTo<ReuseReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(ReuseReport::class, 'report_id');
    }
}
