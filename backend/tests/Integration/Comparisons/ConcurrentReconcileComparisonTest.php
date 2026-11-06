<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use App\Enums\Comparisons\ComparisonSide;
use App\Enums\Lab\LabRunState;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\Comparisons\ComparisonRunFixtures;
use Tests\Support\Comparisons\LabResultFixtures;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\Mdev44\DomainTables;

/**
 * BV208 — Deux processus concurrents réconciliant la même comparaison :
 * finalisation UNIQUE grâce au SELECT ... FOR UPDATE sur comparison_runs.
 * Même approche que ConcurrentLabRunTest (B33) : Symfony\Process + barrière
 * `pg_stat_activity`, pas de pcntl. Les écritures sortent de la transaction
 * de test, on nettoie les tables du domaine via DomainTables::dropAll() avant
 * le rollback de DatabaseMigrations (jamais CASCADE).
 */
final class ConcurrentReconcileComparisonTest extends PostgresTestCase
{
    use ComparisonRunFixtures, DatabaseMigrations, LabResultFixtures, RegistryFixtures;

    protected function tearDown(): void
    {
        try {
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_concurrent_reconciliations_finalize_once(): void
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $definitionId = $this->definition($version);
        $actor = User::factory()->verified()->create();
        $comparison = $this->pendingComparisonRun($profileId, $actor);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Baseline, $version);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Candidate, $version);
        // baseline en échec et candidate en réussite -> improved (vérification
        // explicite que le travail n'est pas une victoire par défaut mais une
        // conclusion déterministe du calculateur appliqué sous verrou).
        $this->recordTerminalResult($comparison, ComparisonSide::Baseline, LabRunState::Failed, ['a' => false]);
        $this->recordTerminalResult($comparison, ComparisonSide::Candidate, LabRunState::Passed, ['a' => true]);

        $db = config('database.connections.pgsql');
        $env = [
            'APP_KEY' => (string) config('app.key'), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'],
            'LAB_ENABLED' => 'true',
        ];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/reconcile-comparison-concurrently.php')], base_path(), $env, $stream, 30);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                while (! str_contains($process->getOutput(), 'READY') && $process->isRunning()) {
                    $process->checkTimeout();
                    usleep(10000);
                }
                $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
            }
            // Barrière : on prend un verrou de ligne sur la comparaison. Les
            // deux processus devront attendre notre release pour avancer leur
            // propre SELECT ... FOR UPDATE.
            DB::beginTransaction();
            DB::select('SELECT id FROM comparison_runs WHERE id = ? FOR UPDATE', [$comparison->id]);
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode(['comparison_id' => $comparison->id], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            $deadline = microtime(true) + 10;
            do {
                foreach ($processes as $process) {
                    $process->isRunning();
                    $process->checkTimeout();
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_bv208_reconciler' AND wait_event_type IN ('Lock', 'LWLock')")->total;
                if ($waiting >= 1) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertGreaterThanOrEqual(1, $waiting, 'Au moins un réconciliateur doit attendre le verrou.');
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = array_values(array_filter(array_map('trim', explode("\n", $process->getOutput()))));
                $outcomes[] = end($lines);
            }
            sort($outcomes);
            // Un seul des deux processus finalise ; l'autre voit un état terminal
            // et retourne already_finalized (idempotent).
            $this->assertSame([
                'already_finalized completed improved',
                'finalized completed improved',
            ], $outcomes, 'Finalisation unique attendue ; sortie observée : '.json_encode($outcomes));
            // Une seule ligne de comparaison, en completed/improved, finished_at non null.
            $row = DB::table('comparison_runs')->where('id', $comparison->id)->first();
            $this->assertNotNull($row);
            $this->assertSame('completed', $row->state);
            $this->assertSame('improved', $row->outcome);
            $this->assertNotNull($row->finished_at);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($streams as $stream) {
                $stream->close();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }
}
