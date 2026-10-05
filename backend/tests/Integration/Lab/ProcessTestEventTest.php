<?php

namespace Tests\Integration\Lab;

use App\Data\Lab\TestEventData;
use App\Models\Lab\TestEvent;
use App\Models\Lab\TestOrder;
use App\Services\Lab\ProcessTestEventService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\PostgresTestCase;

/**
 * Scénarios B1-01 à B1-04 (lot B35) : création nominale, rejeu idempotent,
 * événements distincts et rejet validation sans écriture partielle. Le cas
 * B1-05 (concurrence réelle) est isolé dans ConcurrentTestEventTest.
 */
final class ProcessTestEventTest extends PostgresTestCase
{
    use RefreshDatabase;

    private ProcessTestEventService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProcessTestEventService;
    }

    public function test_b1_01_nominal_creates_event_and_order_in_one_transaction(): void
    {
        $data = $this->canonicalData();

        $result = $this->service->handle($data);

        $this->assertFalse($result->duplicate, 'Le premier appel doit signaler duplicate=false.');
        $this->assertDatabaseCount('test_events', 1);
        $this->assertDatabaseCount('test_orders', 1);
        $this->assertSame($data->runId, $result->order->run_id);
        $this->assertSame($data->orderRef, $result->order->order_ref);
        $this->assertSame($data->amountMinor, $result->order->amount_minor);
        $this->assertSame($data->currency, $result->order->currency);
        $this->assertNotNull($result->order->event);
        $this->assertSame($data->eventId, $result->order->event->event_id);
        $this->assertNotNull($result->order->event->processed_at, "L'événement doit être marqué traité.");
    }

    public function test_b1_02_doublon_returns_the_same_order_without_duplicating_rows(): void
    {
        $data = $this->canonicalData();

        $first = $this->service->handle($data);
        $second = $this->service->handle($data);

        $this->assertFalse($first->duplicate, 'Le premier appel doit signaler duplicate=false.');
        $this->assertTrue($second->duplicate, 'Le second appel doit signaler duplicate=true.');
        $this->assertTrue($first->order->is($second->order), 'Le rejeu doit renvoyer exactement la même commande.');
        $this->assertDatabaseCount('test_events', 1);
        $this->assertDatabaseCount('test_orders', 1);
    }

    public function test_replay_with_different_payload_returns_original(): void
    {
        // Décision à arbitrer : un rejeu avec le même (run_id, event_id) mais un
        // payload différent (montant, référence, devise) renvoie la commande
        // d'origine sans écraser ses données. Ce test épingle le comportement
        // actuel pour éviter une régression silencieuse, sans trancher le choix
        // produit (409 vs. renvoyer l'original).
        $data = $this->canonicalData();
        $first = $this->service->handle($data);

        $divergent = $this->canonicalData([
            'runId' => $data->runId,
            'eventId' => $data->eventId,
            'amountMinor' => $data->amountMinor + 1_000,
            'orderRef' => $data->orderRef.'_changed',
            'currency' => $data->currency === 'EUR' ? 'USD' : 'EUR',
        ]);

        $second = $this->service->handle($divergent);

        $this->assertTrue($second->duplicate);
        $this->assertTrue($first->order->is($second->order));
        $this->assertDatabaseCount('test_events', 1);
        $this->assertDatabaseCount('test_orders', 1);
        $this->assertDatabaseHas('test_events', [
            'run_id' => $data->runId,
            'event_id' => $data->eventId,
            'order_ref' => $data->orderRef,
            'amount_minor' => $data->amountMinor,
            'currency' => $data->currency,
        ]);
    }

    public function test_b1_03_two_distinct_events_produce_two_orders(): void
    {
        $runId = (string) Str::uuid();
        $first = $this->canonicalData(['runId' => $runId, 'eventId' => 'evt_distinct_a', 'orderRef' => 'ord_distinct_a']);
        $second = $this->canonicalData(['runId' => $runId, 'eventId' => 'evt_distinct_b', 'orderRef' => 'ord_distinct_b']);

        $resultA = $this->service->handle($first);
        $resultB = $this->service->handle($second);

        $this->assertFalse($resultA->order->is($resultB->order), 'Deux événements distincts ne doivent pas partager une commande.');
        $this->assertDatabaseCount('test_events', 2);
        $this->assertDatabaseCount('test_orders', 2);
        $this->assertSame('evt_distinct_a', $resultA->order->event?->event_id);
        $this->assertSame('evt_distinct_b', $resultB->order->event?->event_id);
    }

    public function test_b1_03_bis_same_event_id_across_different_runs_are_distinct(): void
    {
        $first = $this->canonicalData(['runId' => (string) Str::uuid(), 'eventId' => 'evt_shared']);
        $second = $this->canonicalData(['runId' => (string) Str::uuid(), 'eventId' => 'evt_shared']);

        $this->service->handle($first);
        $this->service->handle($second);

        $this->assertDatabaseCount('test_events', 2);
        $this->assertDatabaseCount('test_orders', 2);
    }

    /**
     * @param  array{runId?: string, eventId?: string, orderRef?: string, amountMinor?: int, currency?: string, receivedAt?: DateTimeImmutable}  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_b1_04_invalide_rejects_without_any_partial_write(string $case, array $overrides): void
    {
        $overrides['runId'] ??= (string) Str::uuid();
        $data = $this->canonicalData($overrides);

        try {
            $this->service->handle($data);
            $this->fail("La validation du cas « $case » devait échouer.");
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors(), 'La ValidationException doit porter des erreurs.');
        }

        $this->assertDatabaseCount('test_events', 0);
        $this->assertDatabaseCount('test_orders', 0);
        $this->assertSame(0, TestEvent::query()->count());
        $this->assertSame(0, TestOrder::query()->count());
    }

    public function test_order_creation_failure_rolls_back_the_event_insert(): void
    {
        // Atomicité : si la création de la commande échoue (listener creating qui
        // lève), la transaction du service doit annuler l'insertion de l'événement.
        // Aucune ligne orpheline ne doit subsister dans test_events.
        $data = $this->canonicalData();

        TestOrder::creating(static function (): void {
            throw new RuntimeException('Création TestOrder sabotée pour tester le rollback.');
        });

        try {
            try {
                $this->service->handle($data);
                $this->fail("L'exception du listener doit remonter au service.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('sabotée', $exception->getMessage());
            }
        } finally {
            TestOrder::flushEventListeners();
        }

        $this->assertDatabaseCount('test_events', 0);
        $this->assertDatabaseCount('test_orders', 0);
    }

    /**
     * @return iterable<string, array{string, array{runId?: string, eventId?: string, orderRef?: string, amountMinor?: int, currency?: string}}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'amount_minor nul' => ['amount_minor nul', ['amountMinor' => 0]];
        yield 'amount_minor négatif' => ['amount_minor négatif', ['amountMinor' => -100]];
        yield 'devise minuscule' => ['devise minuscule', ['currency' => 'eur']];
        yield 'devise trop longue' => ['devise trop longue', ['currency' => 'EURO']];
        yield 'event_id vide' => ['event_id vide', ['eventId' => '']];
        yield 'event_id blanc' => ['event_id blanc', ['eventId' => '   ']];
        yield 'event_id espaces de bordure' => ['event_id espaces de bordure', ['eventId' => ' evt ']];
        yield 'event_id saut de ligne final' => ['event_id saut de ligne final', ['eventId' => "evt_trailing_newline\n"]];
        yield 'order_ref vide' => ['order_ref vide', ['orderRef' => '']];
        yield 'order_ref saut de ligne final' => ['order_ref saut de ligne final', ['orderRef' => "ord_trailing_newline\n"]];
        yield 'run_id non UUID' => ['run_id non UUID', ['runId' => 'not-a-uuid']];
    }

    /**
     * @param  array{runId?: string, eventId?: string, orderRef?: string, amountMinor?: int, currency?: string, receivedAt?: DateTimeImmutable}  $overrides
     */
    private function canonicalData(array $overrides = []): TestEventData
    {
        return new TestEventData(
            runId: $overrides['runId'] ?? (string) Str::uuid(),
            eventId: $overrides['eventId'] ?? 'evt_nominal_'.Str::random(8),
            orderRef: $overrides['orderRef'] ?? 'ord_nominal_'.Str::random(8),
            amountMinor: $overrides['amountMinor'] ?? 2599,
            currency: $overrides['currency'] ?? 'EUR',
            receivedAt: $overrides['receivedAt'] ?? new DateTimeImmutable('2026-10-05T12:00:00+00:00'),
        );
    }
}
