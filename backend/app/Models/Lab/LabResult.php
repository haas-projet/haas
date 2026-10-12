<?php

declare(strict_types=1);

namespace App\Models\Lab;

use App\Enums\Lab\LabRunState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property LabRunState $state */
final class LabResult extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $guarded = ['*'];

    /** @return BelongsTo<LabRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(LabRun::class, 'run_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['state' => LabRunState::class, 'complete' => 'boolean', 'assertions' => 'array',
            'runtime' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
