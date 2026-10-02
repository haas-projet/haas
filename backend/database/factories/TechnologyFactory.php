<?php

namespace Database\Factories;

use App\Models\Technology;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Technology> */
class TechnologyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['slug' => 'technologie-'.fake()->unique()->numerify('############'), 'name' => 'Technologie de test'];
    }
}
