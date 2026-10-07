<?php

namespace Tests\Unit;

use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class IdempotencyDataTest extends TestCase
{
    private const string KEY = 'fda9a000-3333-4444-8888-123456789abc';

    public function test_key_case_and_object_order_are_canonical_but_lists_and_types_are_distinct(): void
    {
        $this->assertSame((new IdempotencyKey(self::KEY))->hash, (new IdempotencyKey(strtoupper(self::KEY)))->hash);
        $a = new IdempotencyData('POST /api/v1/fixture', new IdempotencyKey(self::KEY), ['b' => 2, 'a' => ['y' => true, 'x' => null]]);
        $b = new IdempotencyData('POST /api/v1/fixture', new IdempotencyKey(self::KEY), ['a' => ['x' => null, 'y' => true], 'b' => 2]);
        $secret = str_repeat('s', 32);
        $this->assertSame($a->fingerprint($secret), $b->fingerprint($secret));
        $hashes = [];
        foreach ([['v' => 1], ['v' => '1'], ['v' => true], ['v' => null], [], ['v' => [1, 2]], ['v' => [2, 1]]] as $payload) {
            $hashes[] = (new IdempotencyData('POST /api/v1/fixture', new IdempotencyKey(self::KEY), $payload))->fingerprint($secret);
        }
        $this->assertCount(7, array_unique($hashes));
        $this->assertNotSame($a->fingerprint($secret), $a->fingerprint(str_repeat('r', 32)));
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_keys_are_rejected_without_including_input_in_error(string $key): void
    {
        $this->assertFalse(IdempotencyKey::valid($key));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Clé d’idempotence invalide.');
        new IdempotencyKey($key);
    }

    /** @return iterable<array{string}> */
    public static function invalidKeys(): iterable
    {
        foreach (['', 'fixture-secret', self::KEY.' ', self::KEY.','.self::KEY, str_repeat('a', 1000), str_replace('-4444-', '-1444-', self::KEY), self::KEY."\n"] as $key) {
            yield [$key];
        }
    }

    /** @param array<array-key, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function test_payloads_are_bounded_and_never_accept_objects_or_floats(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IdempotencyData('POST /api/v1/fixture', new IdempotencyKey(self::KEY), $payload);
    }

    /** @return iterable<array{array<array-key, mixed>}> */
    public static function invalidPayloads(): iterable
    {
        yield [['value' => 1.5]];
        yield [['value' => new stdClass]];
        yield [['value' => str_repeat('é', 65537)]];
        yield [['value' => "\xFF"]];
        $nested = ['value' => 'fixture'];
        for ($i = 0; $i < 17; $i++) {
            $nested = ['nested' => $nested];
        }
        yield [$nested];
    }

    #[DataProvider('invalidTargets')]
    public function test_only_server_normalized_targets_are_accepted(string $target): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IdempotencyData($target, new IdempotencyKey(self::KEY), []);
    }

    /** @return iterable<array{string}> */
    public static function invalidTargets(): iterable
    {
        foreach (['GET /api/v1/fixture', 'post /api/v1/fixture', 'POST https://example.invalid', 'POST /api/v1/fixture?user=1', 'POST /api/v1/../fixture', 'POST /api/v1/fixture/', 'POST /api/v1/'.str_repeat('a', 250)] as $target) {
            yield [$target];
        }
    }

    public function test_stored_result_contains_only_uuid_references_version_and_allowed_status(): void
    {
        $result = new StoredCommandResult(201, ['resource_id' => strtoupper(self::KEY)], 1);
        $this->assertSame(['references' => ['resource_id' => self::KEY], 'version' => 1], $result->response());
        $this->expectException(InvalidArgumentException::class);
        new StoredCommandResult(200, ['body' => 'texte privé de fixture']);
    }

    public function test_unsuccessful_response_cannot_be_memoized(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StoredCommandResult(500, ['resource_id' => self::KEY]);
    }
}
