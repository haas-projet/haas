<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Comparisons;

use App\Enums\Comparisons\ComparisonState;
use PHPUnit\Framework\TestCase;

/**
 * Valeurs string : sources concordantes.
 * - docs/product/ATELIER_COLLABORATIF.md:51 « ComparisonState : queued, running, completed, error, timed_out. »
 * - docs/product/CAHIER_DES_CHARGES.md:1708 (reprise textuelle).
 *
 * Le vocabulaire d'état diffère volontairement de LabRunState : une comparaison termine
 * en completed même quand ses runs enfants sont failed, erreur ou timed_out — la conclusion
 * est ensuite calculée par ComparisonOutcome (lot BV208).
 */
final class ComparisonStateTest extends TestCase
{
    public function test_cases_match_documented_values_in_declared_order(): void
    {
        $values = array_map(static fn (ComparisonState $case): string => $case->value, ComparisonState::cases());

        $this->assertSame(['queued', 'running', 'completed', 'error', 'timed_out'], $values);
    }
}
