<?php

declare(strict_types=1);

namespace App\Models\Lab;

use App\Models\Capsules\CapsuleVersion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $version_id
 * @property string $runner_key
 * @property string $release
 * @property string $module_version
 * @property string $source_commit
 * @property string $code_digest
 * @property string $suite_digest
 * @property string $version_digest
 * @property bool $enabled
 */
final class LabDefinition extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $guarded = ['*'];

    /** @return BelongsTo<CapsuleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CapsuleVersion::class, 'version_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'created_at' => 'immutable_datetime'];
    }
}
