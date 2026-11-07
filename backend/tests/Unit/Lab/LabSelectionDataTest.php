<?php

declare(strict_types=1);

namespace Tests\Unit\Lab;

use App\Data\Lab\LabSelectionData;
use App\Enums\Lab\LabScenario;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LabSelectionDataTest extends TestCase
{
    private const VERSION = '92ad814c-977e-42cc-9ffd-84c69dba51e0';

    public function test_selection_contains_only_a_version_and_a_fixed_scenario(): void
    {
        $data = new LabSelectionData(['capsule_version_id' => self::VERSION, 'scenario' => 'duplicate_event']);

        $this->assertSame(self::VERSION, $data->versionId);
        $this->assertSame(LabScenario::DuplicateEvent, $data->scenario);
        $this->assertSame(['capsule_version_id' => self::VERSION, 'scenario' => 'duplicate_event'], $data->payload());
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('invalidSelections')]
    public function test_untrusted_or_incomplete_selection_is_rejected(array $body): void
    {
        $this->expectException(ValidationException::class);

        new LabSelectionData($body);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidSelections(): iterable
    {
        $valid = ['capsule_version_id' => self::VERSION, 'scenario' => 'duplicate_event'];
        yield 'missing scenario' => [['capsule_version_id' => self::VERSION]];
        yield 'missing version' => [['scenario' => 'duplicate_event']];
        yield 'version array' => [array_replace($valid, ['capsule_version_id' => [self::VERSION]])];
        yield 'unknown scenario' => [array_replace($valid, ['scenario' => 'anything'])];
        yield 'CI scenario is not a web scenario' => [array_replace($valid, ['scenario' => 'concurrent_event'])];
        yield 'scenario array' => [array_replace($valid, ['scenario' => ['duplicate_event']])];
        yield 'scenario boolean' => [array_replace($valid, ['scenario' => true])];
        foreach (['code', 'command', 'url', 'runner_key', 'runner_class', 'path', 'passed', 'observed', 'code_digest'] as $field) {
            yield 'protected '.$field => [$valid + [$field => 'untrusted']];
        }
    }

    public function test_web_scenarios_are_exactly_the_four_fixed_b1_cases(): void
    {
        $this->assertSame(['nominal_event', 'duplicate_event', 'distinct_events', 'invalid_event'],
            array_map(static fn (LabScenario $scenario): string => $scenario->value, LabScenario::cases()));
    }
}
