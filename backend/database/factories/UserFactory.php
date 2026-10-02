<?php

namespace Database\Factories;

use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'handle' => 'membre_'.fake()->unique()->numerify('############'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => null,
            'password' => static::$password ??= Hash::make('mot-de-passe-de-test'),
            'remember_token' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => now()]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AccountStatus::Suspended]);
    }

    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::Moderator]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::Admin]);
    }

    public function demo(): static
    {
        return $this->state(fn (array $attributes) => ['is_demo' => true]);
    }
}
