<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Report extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['lock_version' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
