<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Capsules;

use App\Enums\Capsules\CapsuleVisibility;
use PHPUnit\Framework\TestCase;

/**
 * Visibilité d'une capsule : docs/product/CAHIER_DES_CHARGES.md:869 cite « visibility » sans
 * énumérer les valeurs. Choix retenu : visible/hidden, cohérent avec la modération de profil
 * B30/B31 (masquage sans suppression). À confirmer par le relecteur.
 */
final class CapsuleVisibilityTest extends TestCase
{
    public function test_cases_match_documented_values(): void
    {
        $values = array_map(static fn (CapsuleVisibility $case): string => $case->value, CapsuleVisibility::cases());

        $this->assertSame(['visible', 'hidden'], $values);
    }
}
