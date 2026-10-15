<?php

declare(strict_types=1);

namespace App\Exceptions\Lab;

use RuntimeException;

/**
 * Levée lorsque le membre a consommé ses unités horaires de laboratoire.
 * Le délai Retry-After est calculé à partir de la fenêtre glissante
 * (créneau le plus ancien dans l'heure courante).
 */
final class LabQuotaExceeded extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Quota de lancements laboratoire atteint.');
    }
}
