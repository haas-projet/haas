<?php

namespace Database\Factories;

use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<HelpRequest> */
class HelpRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'title' => Str::padRight('Blocage reproductible sur la file ', 20, 'x'),
            'goal' => Str::padRight('Décrire l\'objectif attendu avec suffisamment de contexte ', 60, 'x'),
            'expected' => Str::padRight('Résultat attendu détaillé pour un lecteur extérieur ', 60, 'x'),
            'observed' => Str::padRight('Comportement observé décrit factuellement sans jugement ', 60, 'x'),
            'attempts' => Str::padRight('Pistes déjà testées, échecs et constats ', 40, 'x'),
            'environment' => 'Linux x86_64 + Laravel 13',
            'state' => 'draft',
            'lock_version' => 1,
        ];
    }
}
