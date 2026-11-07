<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $occurred_at
 * @property string $diagnosis
 * @property string $fix
 * @property string $verification
 * @property string $limits
 */
final class ProposalRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['proposal_version' => 'integer', 'occurred_at' => 'immutable_datetime'];
    }
}
