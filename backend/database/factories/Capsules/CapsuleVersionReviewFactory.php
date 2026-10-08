<?php

declare(strict_types=1);

namespace Database\Factories\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ReviewDecision;
use App\Enums\Identity\Role;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Capsules\CapsuleVersionReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CapsuleVersionReview> */
final class CapsuleVersionReviewFactory extends Factory
{
    protected $model = CapsuleVersionReview::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'version_id' => CapsuleVersion::factory()->state(['state' => CapsuleVersionState::InReview]),
            'reviewer_id' => User::factory()->verified()->state(['role' => Role::Moderator]),
            'decision' => ReviewDecision::RequestChanges,
            'note' => 'Veuillez préciser la procédure et les limites avant resoumission.',
            'created_at' => now()->utc(),
            'reviewed_lock_version' => 1,
        ];
    }
}
