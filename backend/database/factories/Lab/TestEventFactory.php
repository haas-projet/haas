<?php

namespace Database\Factories\Lab;

use App\Models\Lab\TestEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Événement de test entièrement fictif pour la brique B1 du laboratoire.
 * Aucun identifiant réel, aucun montant hérité d'un véritable échange : seules
 * des données canoniques jetables sont produites.
 *
 * @extends Factory<TestEvent>
 */
class TestEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'run_id' => (string) Str::uuid(),
            'event_id' => 'evt_'.fake()->unique()->numerify('############'),
            'order_ref' => 'ord_'.fake()->unique()->numerify('############'),
            'amount_minor' => fake()->numberBetween(100, 100_000),
            'currency' => fake()->randomElement(['EUR', 'USD', 'GBP']),
            'received_at' => now(),
        ];
    }

    public function forRun(string $runId): self
    {
        return $this->state(fn (): array => ['run_id' => $runId]);
    }
}
