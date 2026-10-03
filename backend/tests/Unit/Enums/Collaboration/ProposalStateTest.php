<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Collaboration;

use App\Enums\Collaboration\ProposalState;
use PHPUnit\Framework\TestCase;

final class ProposalStateTest extends TestCase
{
    public function test_cases_match_architecture_values(): void
    {
        // docs/architecture/ARCHITECTURE.md:256
        $values = array_map(static fn (ProposalState $state): string => $state->value, ProposalState::cases());
        $this->assertSame(['proposed', 'accepted', 'not_selected'], $values);
    }
}
