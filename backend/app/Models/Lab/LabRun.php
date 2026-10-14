<?php

declare(strict_types=1);

namespace App\Models\Lab;

use App\Enums\Lab\LabRunState;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property LabRunState $state */
final class LabRun extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $guarded = ['*'];

    /** @return BelongsTo<LabDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(LabDefinition::class, 'definition_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<LabResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(LabResult::class, 'run_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['state' => LabRunState::class, 'created_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime', 'duration_ms' => 'integer'];
    }
}
