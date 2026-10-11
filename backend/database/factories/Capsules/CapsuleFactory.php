<?php

declare(strict_types=1);

namespace Database\Factories\Capsules;

use App\Enums\Capsules\CapsuleVisibility;
use App\Models\Capsules\Capsule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Capsule> */
final class CapsuleFactory extends Factory
{
    protected $model = Capsule::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'slug' => Str::lower('capsule-'.Str::random(12)),
            'source_request_id' => null,
            'owner_id' => User::factory(),
            'editorial_origin' => 'Démonstration éditoriale',
            'visibility' => CapsuleVisibility::Visible,
        ];
    }

    public function fromResolvedRequest(string $helpRequestId): self
    {
        return $this->state(fn () => [
            'source_request_id' => $helpRequestId,
            'editorial_origin' => null,
        ]);
    }

    public function hidden(): self
    {
        return $this->state(fn () => ['visibility' => CapsuleVisibility::Hidden]);
    }
}
