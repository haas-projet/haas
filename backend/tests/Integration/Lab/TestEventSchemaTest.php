<?php

namespace Tests\Integration\Lab;

use App\Models\Lab\TestEvent;
use App\Models\Lab\TestOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class TestEventSchemaTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_both_tables_exist_with_their_columns(): void
    {
        $this->assertTrue(Schema::hasTable('test_events'));
        $this->assertTrue(Schema::hasTable('test_orders'));

        foreach (['id', 'run_id', 'event_id', 'order_ref', 'amount_minor', 'currency', 'received_at', 'processed_at', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('test_events', $column), "Colonne manquante : test_events.$column");
        }
        foreach (['id', 'run_id', 'source_event_id', 'order_ref', 'amount_minor', 'currency', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('test_orders', $column), "Colonne manquante : test_orders.$column");
        }
    }

    public function test_run_event_pair_is_unique_on_events_table(): void
    {
        $runId = (string) Str::uuid();
        TestEvent::factory()->forRun($runId)->create(['event_id' => 'evt_duplicate']);

        try {
            TestEvent::factory()->forRun($runId)->create(['event_id' => 'evt_duplicate']);
            $this->fail('La contrainte unique (run_id, event_id) devait rejeter le doublon.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test_events_run_event_unique', $exception->getMessage());
        }
    }

    public function test_orders_cannot_reference_the_same_event_twice(): void
    {
        $event = TestEvent::factory()->create();
        TestOrder::factory()->fromEvent($event)->create();

        // L'invariant « un événement = au plus une commande » n'implique pas
        // de créer un second événement en coulisses : fromEvent() doit réutiliser
        // l'événement passé en argument, sans retomber sur la définition lazy.
        $this->assertDatabaseCount('test_events', 1);

        try {
            TestOrder::factory()->fromEvent($event)->create();
            $this->fail('La contrainte unique source_event_id devait rejeter le doublon.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test_orders_source_event_unique', $exception->getMessage());
        }
    }

    public function test_amount_minor_must_be_strictly_positive(): void
    {
        $this->assertConstraintViolation('test_events_amount_positive', [
            'event_id' => 'evt_zero',
            'order_ref' => 'ord_zero',
            'amount_minor' => 0,
            'currency' => 'EUR',
        ]);
    }

    public function test_currency_must_be_three_uppercase_letters(): void
    {
        $this->assertConstraintViolation('test_events_currency_format', [
            'event_id' => 'evt_bad_currency',
            'order_ref' => 'ord_bad_currency',
            'amount_minor' => 100,
            'currency' => 'eu',
        ]);
    }

    public function test_event_id_must_be_trimmed_and_not_blank(): void
    {
        $this->assertConstraintViolation('test_events_event_id_trim', [
            'event_id' => '  ',
            'order_ref' => 'ord_blank_event',
            'amount_minor' => 100,
            'currency' => 'EUR',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function assertConstraintViolation(string $expectedConstraint, array $overrides): void
    {
        $payload = array_merge([
            'id' => (string) Str::uuid(),
            'run_id' => (string) Str::uuid(),
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        try {
            DB::table('test_events')->insert($payload);
            $this->fail("La contrainte $expectedConstraint n'a pas été déclenchée.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString($expectedConstraint, $exception->getMessage());
        }
    }
}
