<?php

namespace Database\Factories;

use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HelpRequest> */
class HelpRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'title' => 'Blocage reproductible sur la file',
            'goal' => 'Décrire l\'objectif attendu avec le contexte utile.',
            'expected' => 'Résultat attendu pour un lecteur extérieur.',
            'observed' => 'Comportement observé décrit factuellement.',
            'attempts' => 'Pistes déjà testées, échecs et constats.',
            'environment' => 'Linux x86_64 + Laravel 13',
            'state' => 'draft',
            'lock_version' => 1,
        ];
    }
}
