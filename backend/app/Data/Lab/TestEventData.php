<?php

namespace App\Data\Lab;

use DateTimeImmutable;

/**
 * Payload fictif d'un événement de test B1 reçu par le laboratoire.
 *
 * Les valeurs sont canoniques et jetables ; aucun identifiant ou montant réel
 * ne transite par cet objet. La validation métier (montant strictement positif,
 * devise ISO 4217 alpha-3 majuscule, identifiants non vides et sans espace de
 * bordure) est appliquée par ProcessTestEventService avant toute écriture.
 */
final readonly class TestEventData
{
    public function __construct(
        public string $runId,
        public string $eventId,
        public string $orderRef,
        public int $amountMinor,
        public string $currency,
        public DateTimeImmutable $receivedAt,
    ) {}
}
