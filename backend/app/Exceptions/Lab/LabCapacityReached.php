<?php

declare(strict_types=1);

namespace App\Exceptions\Lab;

use RuntimeException;

/**
 * Levée lorsque le nombre maximal de runs actifs (queued ET running) est
 * atteint. Lecture restrictive : B33 interdit déjà deux running simultanés,
 * B34 interdit aussi la file d'attente pleine et un deuxième démarrage
 * concurrent, pour accueillir les deux enfants séquentiels d'une
 * comparaison sans surprise.
 */
final class LabCapacityReached extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Capacité de lancement laboratoire atteinte.');
    }
}
