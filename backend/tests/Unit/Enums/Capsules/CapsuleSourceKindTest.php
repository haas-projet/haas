<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Capsules;

use App\Enums\Capsules\CapsuleSourceKind;
use PHPUnit\Framework\TestCase;

final class CapsuleSourceKindTest extends TestCase
{
    public function test_cases_match_documented_sources(): void
    {
        $values = array_map(static fn (CapsuleSourceKind $k): string => $k->value, CapsuleSourceKind::cases());

        $this->assertSame(['help_request', 'editorial'], $values);
    }
}
