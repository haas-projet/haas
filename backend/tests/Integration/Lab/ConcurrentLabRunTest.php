<?php

declare(strict_types=1);

namespace Tests\Integration\Lab;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\Lab\RegistryFixtures;

final class ConcurrentLabRunTest extends PostgresTestCase
{
    use DatabaseMigrations, RegistryFixtures;

    protected function tearDown(): void
    {
        // Les processus de concurrence écrivent dans leur propre transaction
        // non comprise dans la transaction de test ; le rollback B33 refuse
        // tout retour si des lignes persistent. On nettoie dans l'ordre des
        // FK avant que DatabaseMigrations ne joue le rollback.
        foreach (['lab_results', 'lab_runs', 'lab_definitions', 'api_idempotency'] as $table) {
            try {
                DB::connection('pgsql')->table($table)->delete();
            } catch (\Throwable) {
                // Table absente : rien à nettoyer.
            }
        }
        parent::tearDown();
    }

    /** @return iterable<string, array{bool}> */
    public static function keyCases(): iterable
    {
        yield 'two different keys' => [false];
        yield 'two identical keys' => [true];
    }

    #[DataProvider('keyCases')]
    public function test_two_concurrent_launches_do_not_exceed_one_active_run(bool $sameKey): void
    {
        config(['lab.enabled' => true]);
        $firstUser = User::factory()->verified()->create();
        $secondUser = $sameKey ? $firstUser : User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->definition($version);
        $db = config('database.connections.pgsql');
        $env = [
            'APP_KEY' => (string) config('app.key'), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'],
            'LAB_ENABLED' => 'true',
        ];
        $key = (string) Str::uuid();
        $inputs = [
            ['user_id' => $firstUser->id, 'version_id' => $version->id, 'scenario' => 'nominal_event', 'key' => $sameKey ? $key : (string) Str::uuid()],
            ['user_id' => $secondUser->id, 'version_id' => $version->id, 'scenario' => $sameKey ? 'nominal_event' : 'duplicate_event', 'key' => $sameKey ? $key : (string) Str::uuid()],
        ];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/start-lab-run-concurrently.php')], base_path(), $env, $stream, 30);
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
            // Barrière : pose un verrou avisé transactionnel que les deux
            // processus devront prendre après leur SELECT pg_advisory_xact_lock
            // via leur transaction d'idempotence. Même valeur de verrou que
            // StartLabRunService::ADVISORY_LOCK.
            DB::beginTransaction();
            DB::select('SELECT pg_advisory_xact_lock(554478)');
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode($inputs[$index], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            $deadline = microtime(true) + 10;
            do {
                foreach ($processes as $process) {
                    $process->isRunning();
                    $process->checkTimeout();
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b34_worker' AND wait_event_type IN ('Lock', 'LWLock')")->total;
                if ($waiting >= 1) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertGreaterThanOrEqual(1, $waiting, 'Au moins un processus doit être en attente de verrou avisé.');
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }
            if ($sameKey) {
                // Même clé, même charge : les deux processus voient le même rejeu.
                $this->assertSame(['ACCEPTED', 'ACCEPTED'], $outcomes);
                $this->assertSame(1, DB::table('lab_runs')->count());
                $this->assertSame(1, DB::table('api_idempotency')->count());
            } else {
                // Clés distinctes : capacité globale = 1, un seul run accepté.
                sort($outcomes);
                $this->assertSame(['ACCEPTED', 'CAPACITY'], $outcomes);
                $this->assertSame(1, DB::table('lab_runs')->count());
            }
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
