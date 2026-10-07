<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\HelpRequests;

use App\Enums\HelpRequests\HelpRequestState;
use PHPUnit\Framework\TestCase;

final class HelpRequestStateTest extends TestCase
{
    public function test_cases_match_architecture_values(): void
    {
        // docs/architecture/ARCHITECTURE.md:255
        $values = array_map(static fn (HelpRequestState $state): string => $state->value, HelpRequestState::cases());
        $this->assertSame(['draft', 'open', 'in_progress', 'resolved', 'archived'], $values);
    }
}
