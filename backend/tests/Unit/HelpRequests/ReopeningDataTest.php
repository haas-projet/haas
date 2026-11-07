<?php

namespace Tests\Unit\HelpRequests;

use App\Data\HelpRequests\ReopeningData;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReopeningDataTest extends TestCase
{
    public function test_normalizes_useful_unicode_reason_and_preserves_integer_version(): void
    {
        $reason = str_repeat('é', 4000);
        $data = new ReopeningData(['lock_version' => 2147483646, 'reason' => "\u{00A0}\u{200B}".$reason."\u{00A0}"]);
        $this->assertSame(['lock_version' => 2147483646, 'reason' => $reason], $data->payload());
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('invalidBodies')]
    public function test_rejects_invalid_data_before_any_service_can_use_it(array $body): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ReopeningData($body);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidBodies(): iterable
    {
        $valid = ['lock_version' => 4, 'reason' => 'Un motif fictif suffisamment détaillé pour expliquer la réouverture.'];
        foreach (['4', 4.0, 4.5, true, null, 0, 2147483647, []] as $index => $version) {
            yield 'version '.$index => [array_replace($valid, ['lock_version' => $version])];
        }
        foreach (['empty' => '', 'short' => str_repeat('a', 19), 'long' => str_repeat('é', 4001),
            'control' => "\x01".$valid['reason'], 'secret' => 'api_key='.str_repeat('a', 40),
            'link only' => '[Explication assez longue](https://example.test/link-only)', 'invisible' => str_repeat("\u{200B}", 40)] as $name => $reason) {
            yield $name => [array_replace($valid, ['reason' => $reason])];
        }
        yield 'server' => [[...$valid, 'reopened_by' => 'b2000000-1111-4444-8888-123456789abc']];
        yield 'missing reason' => [['lock_version' => 4]];
        yield 'missing version' => [['reason' => $valid['reason']]];
    }
}
