<?php

namespace App\Models\Demo;

use Carbon\CarbonImmutable;
use Database\Factories\Demo\DemoOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Commande fictive de la démonstration B2 (lot B38).
 *
 * Aucun paiement réel n'est manipulé. L'instance porte à la fois les
 * empreintes de la clé d'idempotence et de la charge canonique, puis
 * les champs métier figés par `RecordDemoOrderService`.
 *
 * @property string $id
 * @property string $idempotency_key_hash
 * @property string $payload_hash
 * @property string $order_ref
 * @property int $amount_minor
 * @property string $currency
 * @property string $state
 * @property CarbonImmutable $expires_at
 */
#[Fillable([
    'idempotency_key_hash',
    'payload_hash',
    'order_ref',
    'amount_minor',
    'currency',
    'state',
])]
class DemoOrder extends Model
{
    /** @use HasFactory<DemoOrderFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return DemoConnection::NAME;
    }
}
