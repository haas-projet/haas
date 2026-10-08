<?php

declare(strict_types=1);

namespace Database\Factories\Capsules;

use App\Enums\Capsules\ContributionRole;
use App\Models\Capsules\CapsuleContributor;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CapsuleContributor> */
final class CapsuleContributorFactory extends Factory
{
    protected $model = CapsuleContributor::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'version_id' => CapsuleVersion::factory(),
            'user_id' => User::factory(),
            'contribution_role' => ContributionRole::Diagnosis,
        ];
    }

    public function fix(): self
    {
        return $this->state(fn () => ['contribution_role' => ContributionRole::Fix]);
    }

    public function documentation(): self
    {
        return $this->state(fn () => ['contribution_role' => ContributionRole::Documentation]);
    }

    public function test(): self
    {
        return $this->state(fn () => ['contribution_role' => ContributionRole::Test]);
    }

    public function case(): self
    {
        return $this->state(fn () => ['contribution_role' => ContributionRole::Case]);
    }
}
