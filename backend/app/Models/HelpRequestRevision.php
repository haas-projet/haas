<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** @property CarbonImmutable $occurred_at */
class HelpRequestRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['request_version' => 'integer', 'is_public' => 'boolean', 'changed_fields' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
