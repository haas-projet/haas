<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['owner_id' => User::factory()->verified(),
            'name' => 'Projet fictif '.fake()->unique()->numerify('#####'),
            'summary' => 'Projet entièrement fictif utilisé pour vérifier le schéma communautaire.',
            'description' => 'Cette fiche fictive décrit une application de démonstration sans données personnelles. Elle ne certifie aucun résultat technique ni propriété de dépôt.',
            'project_stage' => 'idea', 'publication_state' => 'draft', 'lock_version' => 1,
            'help_sought' => null, 'demo_url' => null, 'repository_url' => null, 'published_at' => null,
            'hidden_at' => null, 'moderation_reason' => null];
    }

    public function published(): static
    {
        // Fixture de schéma seulement ; ne remplace pas la réception de BC03.
        return $this->state(fn (): array => ['publication_state' => 'published', 'published_at' => now()->utc()]);
    }
}
