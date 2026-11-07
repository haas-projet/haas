<?php

declare(strict_types=1);

namespace App\Data\Lab;

use App\Enums\Lab\LabRunState;

/**
 * Lecture publique d'un run : propriétaire + état + horodatage de création.
 * Aucun digest, aucun manifeste et aucun identifiant interne (definition_id,
 * request_key) n'est exposé ; B34 ne diffuse pas la configuration du runner.
 */
final readonly class QueuedLabRun
{
    public function __construct(
        public string $id,
        public string $versionId,
        public LabRunState $state,
        public string $scenario,
        public string $createdAt,
    ) {}
}
