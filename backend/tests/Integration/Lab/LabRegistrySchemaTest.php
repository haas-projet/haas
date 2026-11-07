<?php

declare(strict_types=1);

namespace Tests\Integration\Lab;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\Lab\RegistryFixtures;

final class LabRegistrySchemaTest extends PostgresTestCase
{
    use RefreshDatabase, RegistryFixtures;

    public function test_registry_control_tables_exist_in_the_application_database_without_results(): void
    {
        foreach (['lab_definitions', 'lab_runs', 'lab_results'] as $table) {
            $this->assertTrue(Schema::connection('pgsql')->hasTable($table), $table);
            $this->assertSame(0, DB::connection('pgsql')->table($table)->count());
        }
    }

    public function test_definition_release_is_immutable_but_the_switch_can_be_disabled(): void
    {
        $id = $this->definition($this->publishedVersion());
        $this->rejected(fn () => DB::table('lab_definitions')->where('id', $id)->update(['code_digest' => str_repeat('0', 64)]));
        $this->rejected(fn () => DB::table('lab_definitions')->where('id', $id)->delete());
        DB::table('lab_definitions')->where('id', $id)->update(['enabled' => false]);
        $this->assertFalse(DB::table('lab_definitions')->where('id', $id)->value('enabled'));
    }

    public function test_runner_key_and_version_foreign_key_are_fixed(): void
    {
        $version = $this->publishedVersion();
        $this->rejected(fn () => $this->definition($version, ['runner_key' => 'untrusted.class']));
        $this->rejected(fn () => $this->definition($version, ['version_id' => (string) Str::uuid()]), '23503');
        $this->definition($version);
        $this->rejected(fn () => $this->definition($version), '23505');
    }

    public function test_run_snapshot_is_bound_to_definition_and_cannot_change_or_be_deleted(): void
    {
        $id = $this->queuedRun($this->definition($this->publishedVersion()));
        $this->rejected(fn () => DB::table('lab_runs')->where('id', $id)->update(['version_digest' => str_repeat('0', 64)]));
        $this->rejected(fn () => DB::table('lab_runs')->where('id', $id)->update(['scenario' => 'invalid_event']));
        $this->rejected(fn () => DB::table('lab_runs')->where('id', $id)->delete());
        $row = (array) DB::table('lab_runs')->find($id);
        $row['id'] = (string) Str::uuid();
        $row['request_key'] = (string) Str::uuid();
        $row['code_digest'] = str_repeat('0', 64);
        $this->rejected(fn () => DB::table('lab_runs')->insert($row));
    }

    public function test_global_running_unique_index_and_terminal_history_are_enforced(): void
    {
        $definition = $this->definition($this->publishedVersion());
        $first = $this->queuedRun($definition);
        $second = $this->queuedRun($definition);
        DB::table('lab_runs')->where('id', $first)->update(['state' => 'running', 'started_at' => now()->utc()]);
        $this->rejected(fn () => DB::table('lab_runs')->where('id', $second)->update(['state' => 'running', 'started_at' => now()->utc()]), '23505');
        $this->assertSame(1, DB::table('lab_runs')->where('state', 'running')->count());
        DB::table('lab_runs')->where('id', $first)->update(['state' => 'error', 'finished_at' => now()->utc()]);
        $this->rejected(fn () => DB::table('lab_runs')->where('id', $first)->update(['state' => 'queued', 'started_at' => null, 'finished_at' => null]));
        DB::table('lab_runs')->where('id', $second)->update(['state' => 'running', 'started_at' => now()->utc()]);
        $this->assertSame(1, DB::table('lab_runs')->where('state', 'running')->count());
    }

    public function test_passing_state_requires_a_complete_observation_and_result_is_immutable(): void
    {
        $id = $this->queuedRun($this->definition($this->publishedVersion()));
        DB::table('lab_runs')->where('id', $id)->update(['state' => 'running', 'started_at' => now()->utc()]);
        $this->rejected(fn () => DB::table('lab_runs')->where('id', $id)->update(['state' => 'passed', 'finished_at' => now()->utc(), 'duration_ms' => 1]));
        $result = $this->report($id);
        DB::table('lab_runs')->where('id', $id)->update(['state' => 'passed', 'finished_at' => now()->utc(), 'duration_ms' => 1]);
        $this->rejected(fn () => DB::table('lab_results')->where('id', $result)->update(['runtime' => '{"changed":true}']));
        $this->rejected(fn () => DB::table('lab_results')->where('id', $result)->delete());
        $this->assertSame('passed', DB::table('lab_runs')->where('id', $id)->value('state'));
    }

    #[DataProvider('invalidReports')]
    public function test_malformed_or_inconsistent_reports_are_refused(string $reason): void
    {
        $id = $this->queuedRun($this->definition($this->publishedVersion()));
        DB::table('lab_runs')->where('id', $id)->update(['state' => 'running', 'started_at' => now()->utc()]);
        $changes = match ($reason) {
            'empty assertions' => ['assertions' => '[]'],
            'malformed assertion' => ['assertions' => '[true]'],
            'passed with failed assertion' => ['assertions' => '[{"key":"fixture","passed":false,"expected":1,"observed":0}]'],
            'failed without failed assertion' => ['state' => 'failed'],
            'technical error complete' => ['state' => 'error'],
            'runtime array' => ['runtime' => '[]'],
            'oversized payload' => ['runtime' => json_encode(['fixture' => str_repeat('x', 60000)], JSON_THROW_ON_ERROR)],
            default => throw new \LogicException,
        };
        $this->rejected(fn () => $this->report($id, $changes));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidReports(): iterable
    {
        foreach (['empty assertions', 'malformed assertion', 'passed with failed assertion', 'failed without failed assertion', 'technical error complete', 'runtime array', 'oversized payload'] as $reason) {
            yield $reason => [$reason];
        }
    }

    public function test_incomplete_result_cannot_be_finalized_as_a_different_technical_state(): void
    {
        $id = $this->queuedRun($this->definition($this->publishedVersion()));
        $this->report($id, ['state' => 'error', 'complete' => false, 'assertions' => '[]']);
        $this->rejected(fn () => DB::table('lab_runs')->where('id', $id)->update(['state' => 'timed_out', 'finished_at' => now()->utc()]));
    }

    public function test_recorded_technical_result_cannot_be_reclaimed_as_running(): void
    {
        $id = $this->queuedRun($this->definition($this->publishedVersion()));
        $this->report($id, ['state' => 'error', 'complete' => false, 'assertions' => '[]']);
        $this->rejected(fn () => DB::table('lab_runs')->where('id', $id)->update(['state' => 'running', 'started_at' => now()->utc()]));
    }

    public function test_downgrade_refuses_known_registry_and_preserves_rows_and_guards(): void
    {
        $definition = $this->definition($this->publishedVersion());
        $migration = require database_path('migrations/2026_10_11_230000_b33_lab_control_registry.php');
        try {
            $migration->down();
            $this->fail('Un registre connu ne doit pas être supprimé par rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('conserver', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('lab_results'));
        $this->assertSame($definition, DB::table('lab_definitions')->value('id'));
        $this->rejected(fn () => DB::table('lab_definitions')->delete());
    }

    /** @param array<string, mixed> $changes */
    private function report(string $runId, array $changes = []): string
    {
        $id = (string) Str::uuid();
        DB::table('lab_results')->insert(array_replace([
            'id' => $id, 'run_id' => $runId, 'state' => 'passed', 'complete' => true,
            'assertions' => '[{"key":"fixture","passed":true,"expected":1,"observed":1}]',
            'runtime' => '{"php":"fixture","postgresql":"fixture"}', 'created_at' => now()->utc(),
        ], $changes));

        return $id;
    }

    /** @param \Closure(): mixed $operation */
    private function rejected(\Closure $operation, string $sqlState = '23514'): void
    {
        try {
            DB::connection('pgsql')->transaction(fn () => $operation());
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0] ?? null);

            return;
        }
        $this->fail('La garde SQL doit refuser cette écriture.');
    }
}
