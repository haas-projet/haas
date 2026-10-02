<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Profile> */
class ProfileFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'bio' => '', 'country' => null, 'primary_language' => 'fr', 'github_url' => null];
    }
}
