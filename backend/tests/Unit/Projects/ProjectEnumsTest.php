<?php

namespace Tests\Unit\Projects;

use App\Enums\Identity\HelpAvailability;
use App\Enums\Projects\ProjectPublicationState;
use App\Enums\Projects\ProjectStage;
use BackedEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProjectEnumsTest extends TestCase
{
    /** @param class-string<BackedEnum> $enum
     * @param  list<string>  $values
     */
    #[DataProvider('enums')]
    public function test_only_the_documented_states_are_available(string $enum, array $values): void
    {
        $this->assertTrue(enum_exists($enum), 'Le contrat BC01 doit être représenté par un enum.');
        $this->assertSame($values, array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases()));
        $this->assertNull($enum::tryFrom('unknown'));
        $this->assertNull($enum::tryFrom('online'));
        foreach ($values as $value) {
            $this->assertSame($value, $enum::from($value)->value);
        }
    }

    /** @return iterable<string, array{class-string<BackedEnum>, list<string>}> */
    public static function enums(): iterable
    {
        yield 'declared project stage' => [ProjectStage::class, ['idea', 'prototype', 'in_development', 'launched']];
        yield 'publication separate from stage' => [ProjectPublicationState::class, ['draft', 'published', 'archived']];
        yield 'availability never online' => [HelpAvailability::class, ['not_specified', 'available', 'unavailable']];
    }
}
