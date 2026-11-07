<?php

declare(strict_types=1);

namespace App\Models\Comparisons;

use App\Enums\Comparisons\ComparisonOutcome;
use App\Enums\Comparisons\ComparisonState;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property string $actor_id
 * @property string $request_key
 * @property ComparisonState $state
 * @property ComparisonOutcome|null $outcome
 * @property string $input_digest
 * @property string $seed
 * @property array<string, mixed> $env_snapshot
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable $created_at
 */
final class ComparisonRun extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $guarded = ['*'];

    /** @return BelongsTo<ComparisonProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ComparisonProfile::class, 'profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => ComparisonState::class,
            'outcome' => ComparisonOutcome::class,
            'env_snapshot' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
