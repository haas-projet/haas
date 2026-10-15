<?php

declare(strict_types=1);

namespace App\Models\Comparisons;

use App\Models\Capsules\CapsuleVersion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $family_key
 * @property array<string, mixed> $baseline_manifest
 * @property string $candidate_version_id
 * @property string $suite_digest
 * @property string $input_schema_version
 * @property bool $enabled
 */
final class ComparisonProfile extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $guarded = ['*'];

    /** @return BelongsTo<CapsuleVersion, $this> */
    public function candidateVersion(): BelongsTo
    {
        return $this->belongsTo(CapsuleVersion::class, 'candidate_version_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'baseline_manifest' => 'array',
            'enabled' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }
}
