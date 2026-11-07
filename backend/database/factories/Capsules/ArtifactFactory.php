<?php

declare(strict_types=1);

namespace Database\Factories\Capsules;

use App\Enums\Capsules\ArtifactDistributionStatus;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\CapsuleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Artifact> */
final class ArtifactFactory extends Factory
{
    protected $model = Artifact::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'version_id' => CapsuleVersion::factory(),
            'private_path' => 'capsules/'.Str::random(16).'/kit.zip',
            'sha256' => hash('sha256', (string) Str::uuid()),
            'size' => 2048,
            'distribution_status' => ArtifactDistributionStatus::Inactive,
            'notices_path' => null,
        ];
    }

    public function approved(): self
    {
        return $this->state(fn () => ['distribution_status' => ArtifactDistributionStatus::Approved]);
    }
}
