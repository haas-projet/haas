<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Comparisons;

use App\Enums\Comparisons\ComparisonOutcome;
use PHPUnit\Framework\TestCase;

/**
 * Valeurs string : sources concordantes.
 * - docs/product/ATELIER_COLLABORATIF.md:51 « ComparisonOutcome : improved, unchanged, regressed, mixed, inconclusive. »
 * - docs/product/CAHIER_DES_CHARGES.md:319 table « Conclusion | improved, unchanged, regressed, mixed, inconclusive. »
 *
 * La table de vérité reliant les assertions des deux runs aux conclusions sera portée
 * par ComparisonOutcomeCalculator (lot BV208). Cet enum ne porte que les valeurs.
 */
final class ComparisonOutcomeTest extends TestCase
{
    public function test_cases_match_documented_values_in_declared_order(): void
    {
        $values = array_map(static fn (ComparisonOutcome $case): string => $case->value, ComparisonOutcome::cases());

        $this->assertSame(['improved', 'unchanged', 'regressed', 'mixed', 'inconclusive'], $values);
    }
}
