<?php

namespace Tests\Unit\HelpRequests;

use App\Data\HelpRequests\ResolutionData;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResolutionDataTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function valid(): array
    {
        return ['proposal_id' => 'a1900000-3333-4444-8888-123456789abc', 'lock_version' => 2, 'proposal_lock_version' => 1,
            'environment' => 'PHP 8.5 — contexte déclaré localement', 'validation_note' => 'La solution m’a aidé dans cet exemple entièrement fictif.'];
    }

    public function test_normalization_keeps_the_selected_content_version_and_context(): void
    {
        $data = new ResolutionData([...self::valid(), 'proposal_id' => strtoupper(self::valid()['proposal_id']),
            'proposal_lock_version' => 2147483647, 'environment' => "\u{200B}  é\u{00A0}",
            'validation_note' => "\u{200B} ".str_repeat('é', 4000)." \u{200B}"]);
        self::assertSame(self::valid()['proposal_id'], $data->proposalId);
        self::assertSame(2147483647, $data->proposalLockVersion);
        self::assertSame('é', $data->environment);
        self::assertSame(str_repeat('é', 4000), $data->validationNote);
        self::assertSame(2, $data->payload()['lock_version']);
        self::assertCount(5, $data->payload());
    }

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function rejected(): iterable
    {
        foreach (['proposal_id', 'lock_version', 'proposal_lock_version', 'environment', 'validation_note'] as $field) {
            $data = self::valid();
            unset($data[$field]);
            yield 'missing '.$field => [$data];
            yield 'array '.$field => [[...self::valid(), $field => []]];
            yield 'null '.$field => [[...self::valid(), $field => null]];
        }
        foreach (['lock_version', 'proposal_lock_version'] as $field) {
            foreach ([0, -1, 1.0, '1', true, 2147483648] as $value) {
                yield $field.' '.json_encode($value) => [[...self::valid(), $field => $value]];
            }
        }
        yield 'parent overflow' => [[...self::valid(), 'lock_version' => 2147483647]];
        yield 'foreign field' => [[...self::valid(), 'accepted_by' => self::valid()['proposal_id']]];
        yield 'malformed id' => [[...self::valid(), 'proposal_id' => 'not-a-uuid']];
        foreach (['environment', 'validation_note'] as $field) {
            foreach (["\x0B", "\x0C", "\x00", "\x7F"] as $control) {
                yield $field.' control '.ord($control) => [[...self::valid(), $field => $control.self::valid()[$field]]];
            }
            yield $field.' format only' => [[...self::valid(), $field => str_repeat("\u{2060}", 24)]];
            yield $field.' invalid UTF8' => [[...self::valid(), $field => "\xC3\x28".self::valid()[$field]]];
        }
        yield 'environment too long' => [[...self::valid(), 'environment' => str_repeat('é', 256)]];
        yield 'note too short' => [[...self::valid(), 'validation_note' => str_repeat('é', 19)]];
        yield 'note too long' => [[...self::valid(), 'validation_note' => str_repeat('é', 4001)]];
        yield 'note only links' => [[...self::valid(), 'validation_note' => '[documentation sans explication](https://example.test/fictif)']];
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('rejected')]
    public function test_rejects_invalid_raw_input_without_coercing_server_fields(array $data): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ResolutionData($data);
    }
}
