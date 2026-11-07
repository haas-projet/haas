<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $request_id
 * @property string $resolution_id
 * @property string $reopened_by
 * @property string $reason
 * @property int $request_version_before
 * @property int $request_version_after
 * @property CarbonImmutable $reopened_at
 */
#[Fillable([])]
final class RequestReopening extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @return BelongsTo<HelpRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class, 'request_id');
    }

    /** @return BelongsTo<Resolution, $this> */
    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class, 'resolution_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['request_version_before' => 'integer', 'request_version_after' => 'integer', 'reopened_at' => 'immutable_datetime'];
    }
}
