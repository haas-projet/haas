<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Capsules;

use App\Enums\Capsules\ContributionRole;
use PHPUnit\Framework\TestCase;

/**
 * Rôle de contribution. Valeurs issues de l'union de deux citations littérales du cahier :
 * - docs/product/CAHIER_DES_CHARGES.md:364 « cas, diagnostic, correctif, documentation »
 * - docs/product/CAHIER_DES_CHARGES.md:508 « diagnostic, correction, documentation, test »
 * Mapping ASCII : diagnosis/fix/documentation/test/case. La revue indépendante (B24) est
 * portée par capsule_versions.reviewer_id, pas par ce pivot.
 */
final class ContributionRoleTest extends TestCase
{
    public function test_cases_match_documented_values(): void
    {
        $values = array_map(static fn (ContributionRole $case): string => $case->value, ContributionRole::cases());

        $this->assertSame(['diagnosis', 'fix', 'documentation', 'test', 'case'], $values);
    }
}
