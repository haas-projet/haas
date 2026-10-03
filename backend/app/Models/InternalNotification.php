<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $kind
 * @property string $recipient_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $read_at
 */
final class InternalNotification extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'read_at' => 'immutable_datetime'];
    }
}
