<?php

declare(strict_types=1);

namespace App\Models\Capsules;

use App\Enums\Capsules\ArtifactDistributionStatus;
use Database\Factories\Capsules\ArtifactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ArtifactDistributionStatus $distribution_status
 */
#[Fillable([])]
class Artifact extends Model
{
    /** @use HasFactory<ArtifactFactory> */
    use HasFactory, HasUuids;

    /** @var array<string, string> */
    protected $casts = [
        'distribution_status' => ArtifactDistributionStatus::class,
        'size' => 'integer',
    ];

    /** @return BelongsTo<CapsuleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CapsuleVersion::class, 'version_id');
    }
}
