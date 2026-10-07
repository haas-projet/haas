<?php

namespace Database\Factories\Demo;

use App\Models\Demo\DemoOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Commande fictive de la démonstration B2 (lot B38).
 *
 * Les hashes générés par défaut respectent la contrainte CHECK hex 64 : des
 * SHA-256 d'UUID aléatoires sont utilisés comme valeurs canoniques jetables.
 * Aucun paiement réel ni identifiant client HAAS n'est manipulé.
 *
 * @extends Factory<DemoOrder>
 */
class DemoOrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $createdAt = now();

        return [
            'idempotency_key_hash' => hash('sha256', (string) Str::uuid()),
            'payload_hash' => hash('sha256', (string) Str::uuid()),
            'order_ref' => 'demo-'.$this->faker->numerify('####'),
            'amount_minor' => $this->faker->numberBetween(100, 1_000_000),
            'currency' => 'EUR',
            'state' => 'confirmed',
            'created_at' => $createdAt,
            'expires_at' => $createdAt->copy()->addDay(),
        ];
    }
}
