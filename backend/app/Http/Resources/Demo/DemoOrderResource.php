<?php

namespace App\Http\Resources\Demo;

use App\Models\Demo\DemoOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection publique d'une commande fictive B2 (lot B38).
 *
 * Expose uniquement les champs métier fictifs. Les empreintes d'idempotence
 * et de charge, qui ne sont pas des données utilisateur, ne sortent jamais
 * du serveur. Aucun lien avec un compte HAAS, aucune donnée privée.
 *
 * @mixin DemoOrder
 */
final class DemoOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_ref' => $this->order_ref,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'state' => $this->state,
        ];
    }
}
