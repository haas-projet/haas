<?php

namespace Tests\Unit;

use App\Data\Audit\ProfileRevisionData;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProfileRevisionDataTest extends TestCase
{
    public function test_only_changed_field_names_cross_the_audit_boundary(): void
    {
        $this->assertSame(['bio', 'technology_ids'], (new ProfileRevisionData(['bio', 'technology_ids']))->changedFields);
        $this->assertSame([], (new ProfileRevisionData([]))->changedFields);
    }

    /** @param array<array-key, mixed> $fields */
    #[DataProvider('invalidFields')]
    public function test_unknown_sensitive_or_duplicate_fields_are_rejected(array $fields): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProfileRevisionData($fields);
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidFields(): iterable
    {
        foreach (['password', 'token', 'cookie', 'email', 'ip_address', 'request_body', 'actor_id', 'occurred_at', 'metadata'] as $field) {
            yield $field => [[$field]];
        }
        yield 'duplicate' => [['bio', 'bio']];
        yield 'nested' => [[['bio' => 'fixture']]];
        yield 'number' => [[42]];
        yield 'associative' => [['bio' => 'fixture']];
    }
}
