<?php

declare(strict_types=1);

namespace App\Data\Comparisons;

use App\Enums\Lab\LabScenario;

/** DTO BV206 : charge publique POST /comparisons. Rien d'exécutable. */
final class StartComparisonData
{
    public function __construct(
        public readonly string $comparisonProfileId,
        public readonly LabScenario $scenario,
    ) {}

    /** @return array<string, string> */
    public function payload(): array
    {
        return [
            'comparison_profile_id' => $this->comparisonProfileId,
            'scenario_key' => $this->scenario->value,
        ];
    }
}
