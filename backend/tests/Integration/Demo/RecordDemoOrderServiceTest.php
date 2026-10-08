<?php

namespace Tests\Integration\Demo;

use App\Data\Demo\DemoOrderData;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Models\Demo\DemoOrder;
use App\Services\Demo\RecordDemoOrderService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DemoPostgresTestCase;
use Tests\Support\RefreshDemoDatabase;

/**
 * Scénarios B2 (lot B38) sur le service d'enregistrement : création
 * nominale, rejeu idempotent et rejet d'un payload divergent. Les
 * entrées invalides doivent être rejetées sans écriture (ceinture et
 * bretelles de la validation HTTP).
 */
final class RecordDemoOrderServiceTest extends DemoPostgresTestCase
{
    use RefreshDemoDatabase;

    private RecordDemoOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(RecordDemoOrderService::class);
    }

    public function test_nominal_creates_one_order_marked_confirmed(): void
    {
        $data = $this->canonicalData();
        $key = (string) Str::uuid();

        $result = $this->service->handle($data, $key);

        $this->assertFalse($result->replay, 'Le premier appel doit renvoyer replay=false.');
        $this->assertSame('confirmed', $result->order->state);
        $this->assertSame($data->orderRef, $result->order->order_ref);
        $this->assertSame($data->amountMinor, $result->order->amount_minor);
        $this->assertSame($data->currency, $result->order->currency);
        $this->assertDatabaseCount('demo_orders', 1);
    }

    public function test_replay_with_same_key_and_same_payload_returns_the_same_order(): void
    {
        $data = $this->canonicalData();
        $key = (string) Str::uuid();

        $first = $this->service->handle($data, $key);
        $second = $this->service->handle($data, $key);

        $this->assertFalse($first->replay);
        $this->assertTrue($second->replay, 'Le rejeu identique doit renvoyer replay=true.');
        $this->assertTrue($first->order->is($second->order), 'Le rejeu doit renvoyer la même commande.');
        $this->assertDatabaseCount('demo_orders', 1);
    }

    public function test_replay_with_same_key_and_divergent_payload_is_rejected_without_writing(): void
    {
        $data = $this->canonicalData();
        $key = (string) Str::uuid();
        $this->service->handle($data, $key);

        $divergent = new DemoOrderData(
            orderRef: $data->orderRef,
            amountMinor: $data->amountMinor + 100,
            currency: $data->currency,
        );

        try {
            $this->service->handle($divergent, $key);
            $this->fail('Un rejeu avec payload divergent doit lever IdempotencyConflict.');
        } catch (IdempotencyConflict) {
            // Le conflit doit se produire avant toute écriture supplémentaire.
        }

        $this->assertDatabaseCount('demo_orders', 1);
        $stored = DemoOrder::query()->first();
        $this->assertNotNull($stored);
        $this->assertSame($data->orderRef, $stored->order_ref, 'La commande d’origine ne doit pas être écrasée.');
        $this->assertSame($data->amountMinor, $stored->amount_minor);
    }

    public function test_two_distinct_keys_create_two_distinct_orders_even_with_same_body(): void
    {
        $data = $this->canonicalData();

        $firstKey = (string) Str::uuid();
        $secondKey = (string) Str::uuid();

        $first = $this->service->handle($data, $firstKey);
        $second = $this->service->handle($data, $secondKey);

        $this->assertFalse($first->replay);
        $this->assertFalse($second->replay);
        $this->assertFalse($first->order->is($second->order), 'Deux clés distinctes doivent produire deux commandes.');
        $this->assertDatabaseCount('demo_orders', 2);
    }

    #[DataProvider('invalidData')]
    public function test_invalid_payload_is_rejected_before_any_write(DemoOrderData $data): void
    {
        $key = (string) Str::uuid();

        try {
            $this->service->handle($data, $key);
            $this->fail('Un payload invalide doit lever ValidationException.');
        } catch (ValidationException) {
            // Attendu : la ceinture `guard()` refuse avant toute écriture.
        }

        $this->assertDatabaseCount('demo_orders', 0);
    }

    /** @return iterable<string, array{DemoOrderData}> */
    public static function invalidData(): iterable
    {
        yield 'amount zero' => [new DemoOrderData('demo-0001', 0, 'EUR')];
        yield 'amount negative' => [new DemoOrderData('demo-0001', -1, 'EUR')];
        yield 'currency lowercase' => [new DemoOrderData('demo-0001', 1_299, 'eur')];
        yield 'currency wrong length' => [new DemoOrderData('demo-0001', 1_299, 'EU')];
        yield 'empty order_ref' => [new DemoOrderData('', 1_299, 'EUR')];
    }

    public function test_invalid_idempotency_key_is_rejected_before_any_write(): void
    {
        $data = $this->canonicalData();

        try {
            $this->service->handle($data, 'not-a-uuid');
            $this->fail('Une clé invalide doit lever une exception avant toute écriture.');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseCount('demo_orders', 0);
        }
    }

    private function canonicalData(): DemoOrderData
    {
        return new DemoOrderData(
            orderRef: 'demo-0001',
            amountMinor: 1_299,
            currency: 'EUR',
        );
    }
}
