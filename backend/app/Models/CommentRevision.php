<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** @property CarbonImmutable $occurred_at */
final class CommentRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['comment_version' => 'integer', 'occurred_at' => 'immutable_datetime'];
    }
}
