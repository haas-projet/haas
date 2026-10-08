<?php

namespace App\Data\Demo;

/**
 * Payload fictif d'une commande de démonstration B2 (lot B38).
 *
 * Les valeurs sont canoniques et jetables ; aucun identifiant client HAAS,
 * montant réel ou session de la plateforme n'y transite. La validation
 * d'entrée est portée par `RecordDemoOrderRequest` (HTTP) et re-vérifiée
 * par `RecordDemoOrderService::guard()` avant toute écriture (ceinture et
 * bretelles indépendantes du transport).
 */
final readonly class DemoOrderData
{
    public function __construct(
        public string $orderRef,
        public int $amountMinor,
        public string $currency,
    ) {}

    /**
     * Projection canonique utilisée pour l'empreinte d'idempotence.
     * L'ordre des clés est fixé ici une fois pour toutes ; le tri
     * récursif de IdempotencyData assure que des rejeux identiques
     * produisent une empreinte identique.
     *
     * @return array<string, int|string>
     */
    public function canonical(): array
    {
        return [
            'order_ref' => $this->orderRef,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency,
        ];
    }
}
