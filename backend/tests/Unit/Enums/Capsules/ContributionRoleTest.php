<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Capsules;

use App\Enums\Capsules\ContributionRole;
use PHPUnit\Framework\TestCase;

/**
 * Rôle de contribution : docs/product/CAHIER_DES_CHARGES.md:871 cite « contribution_role » sans
 * énumérer les valeurs. Choix retenu : author/reviewer/contributor/maintainer, à confirmer par
 * le relecteur. Le rôle « reviewer » est documentaire ici ; la revue indépendante (B24) sera
 * posée sur capsule_versions.reviewer_id, pas sur ce pivot.
 */
final class ContributionRoleTest extends TestCase
{
    public function test_cases_match_documented_values(): void
    {
        $values = array_map(static fn (ContributionRole $case): string => $case->value, ContributionRole::cases());

        $this->assertSame(['author', 'reviewer', 'contributor', 'maintainer'], $values);
    }
}
