<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Capsules;

use App\Enums\Capsules\ArtifactDistributionStatus;
use PHPUnit\Framework\TestCase;

/**
 * Distribution d'artefact : docs/execution/tasks.json:329 « distribution_status inactif tant que
 * les conditions d'évaluation ne sont pas approuvées ». Choix retenu : inactive (défaut) et
 * approved, à confirmer par le relecteur.
 */
final class ArtifactDistributionStatusTest extends TestCase
{
    public function test_cases_match_documented_values_and_default_is_inactive_first(): void
    {
        $values = array_map(static fn (ArtifactDistributionStatus $case): string => $case->value, ArtifactDistributionStatus::cases());

        $this->assertSame(['inactive', 'approved'], $values);
    }
}
