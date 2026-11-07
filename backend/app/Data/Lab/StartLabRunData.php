<?php

declare(strict_types=1);

namespace App\Data\Lab;

use App\Enums\Lab\LabScenario;

/**
 * Charge canonique d'un lancement B34. Les règles de validation (champs
 * inconnus, UUID, scénario) vivent dans StartLabRunRequest/LabSelectionData ;
 * ce DTO ne manipule que des valeurs déjà filtrées.
 */
final readonly class StartLabRunData
{
    public function __construct(public string $versionId, public LabScenario $scenario) {}

    /** @return array{capsule_version_id: string, scenario: string} */
    public function payload(): array
    {
        return ['capsule_version_id' => $this->versionId, 'scenario' => $this->scenario->value];
    }
}
