<?php

namespace App\Data\Lab;

use App\Models\Lab\TestOrder;

/**
 * Résultat typé renvoyé par ProcessTestEventService::handle.
 *
 * `order` est la commande fictive (nouvellement créée ou récupérée), `duplicate`
 * indique si l'appel courant a reçu un rejeu d'un `(run_id, event_id)` déjà
 * traité. Les deux informations sont nécessaires pour distinguer B1-01 (nominal)
 * de B1-02 (doublon) sans comparer deux instances séparément.
 */
final readonly class ProcessedTestEvent
{
    public function __construct(
        public TestOrder $order,
        public bool $duplicate,
    ) {}
}
