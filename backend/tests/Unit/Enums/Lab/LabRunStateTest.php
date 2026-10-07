<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Lab;

use App\Enums\Lab\LabRunState;
use PHPUnit\Framework\TestCase;

/**
 * Valeurs string : sources concordantes.
 * - docs/architecture/ARCHITECTURE.md:258 « LabRunState : queued, running, passed, failed, error, timed_out. »
 * - HAAS_CODEX_MASTER.md:168 table « Run lab | queued, running, passed, failed, error, timed_out ».
 *
 * Les transitions d'un run de laboratoire sont décidées par le worker restreint et les Services
 * B33–B37 ; aucune transition n'est inscrite ici tant que ces lots n'ont pas défini leurs
 * invariants (claim, lease, timeout technique > 20 s, finalisation unique).
 */
final class LabRunStateTest extends TestCase
{
    public function test_cases_match_documented_values_in_declared_order(): void
    {
        $values = array_map(static fn (LabRunState $case): string => $case->value, LabRunState::cases());

        $this->assertSame(['queued', 'running', 'passed', 'failed', 'error', 'timed_out'], $values);
    }
}
