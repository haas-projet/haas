<?php

namespace App\Data\Demo;

use App\Models\Demo\DemoOrder;

/**
 * Résultat typé d'un enregistrement de commande fictive B2.
 *
 * `replay=true` indique que la même clé d'idempotence et la même charge ont
 * déjà produit cette commande : le rejeu renvoie exactement la ligne stockée.
 * Un rejeu avec une charge différente n'arrive jamais ici : il est converti
 * en `IdempotencyConflict` par le service, lui-même rendu 409 par le socle
 * HTTP commun.
 */
final readonly class RecordedDemoOrder
{
    public function __construct(
        public DemoOrder $order,
        public bool $replay,
    ) {}
}
