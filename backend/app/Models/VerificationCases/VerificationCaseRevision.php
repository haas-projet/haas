<?php

declare(strict_types=1);

namespace App\Models\VerificationCases;

use Database\Factories\VerificationCases\VerificationCaseRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $revision_no
 * @property array<string, mixed> $body
 * @property string $digest
 * @property Carbon|null $submitted_at
 */
#[Fillable([])]
class VerificationCaseRevision extends Model
{
    /** @use HasFactory<VerificationCaseRevisionFactory> */
    use HasFactory, HasUuids;

    /** @var array<string, string> */
    protected $casts = [
        'revision_no' => 'integer',
        'body' => 'array',
        'submitted_at' => 'datetime',
    ];

    /** @return BelongsTo<VerificationCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(VerificationCase::class, 'case_id');
    }
}
