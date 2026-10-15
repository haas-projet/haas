<?php

declare(strict_types=1);

namespace Database\Factories\VerificationCases;

use App\Enums\VerificationCases\VerificationCaseState;
use App\Models\HelpRequest;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VerificationCase> */
final class VerificationCaseFactory extends Factory
{
    protected $model = VerificationCase::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'help_request_id' => HelpRequest::factory(),
            'capsule_version_id' => null,
            'state' => VerificationCaseState::Draft,
            'current_revision' => 0,
            'lock_version' => 1,
        ];
    }

    public function submitted(): self
    {
        return $this->state(fn () => [
            'state' => VerificationCaseState::Submitted,
            'current_revision' => 1,
        ]);
    }

    public function reviewed(): self
    {
        return $this->state(fn () => [
            'state' => VerificationCaseState::Reviewed,
            'current_revision' => 1,
        ]);
    }
}
