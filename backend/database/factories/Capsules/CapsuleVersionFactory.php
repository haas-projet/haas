<?php

declare(strict_types=1);

namespace Database\Factories\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CapsuleVersion> */
final class CapsuleVersionFactory extends Factory
{
    protected $model = CapsuleVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'capsule_id' => Capsule::factory(),
            'version_label' => '1.0.0',
            'body' => "## Procédure\n\nÉtape documentée.",
            'limits' => 'Portée volontairement réduite au scénario de référence.',
            'state' => CapsuleVersionState::Draft,
            'reviewer_id' => null,
            'published_at' => null,
            'lock_version' => 1,
        ];
    }

    public function inReview(User $reviewer): self
    {
        return $this->state(fn () => [
            'state' => CapsuleVersionState::InReview,
            'reviewer_id' => $reviewer->id,
        ]);
    }

    public function published(User $reviewer): self
    {
        return $this->state(fn () => [
            'state' => CapsuleVersionState::Published,
            'reviewer_id' => $reviewer->id,
            'published_at' => now(),
        ]);
    }
}
