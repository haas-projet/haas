<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\Comparisons\ComparisonFixtures;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\Mdev44\DomainTables;

/**
 * BV206 — concurrence sur la réservation d'une comparaison. Deux processus
 * attendent la même barrière via pg_advisory_xact_lock (same ADVISORY_LOCK =
 * 554478 que le lanceur simple). Les asserts valident :
 * - deux comparaisons simultanées = une seule acceptée (un seul emplacement
 *   global, règle « un run réellement actif au maximum ») ;
 * - deux POST simultanés de même clé = une seule comparaison + deux enfants ;
 * - run isolé et comparaison simultanés = un seul accepté.
 */
final class ConcurrentStartComparisonTest extends PostgresTestCase
{
    use ComparisonFixtures, DatabaseMigrations, RegistryFixtures;

    protected function tearDown(): void
    {
        try {
            // Les processus de concurrence écrivent lab_runs / comparison_runs
            // hors de la transaction de test. B33 refuse DELETE et le down() de
            // B33 refuse lignes résiduelles ; on utilise le même déblocage
            // qu'en B34 (ConcurrentLabRunTest : DomainTables::dropAll()).
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_concurrent_comparisons_do_not_exceed_one_active_slot(): void
    {
        config(['lab.enabled' => true]);
        $firstUser = User::factory()->verified()->create();
        $secondUser = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $this->definition($version);
        $inputs = [
            ['user_id' => $firstUser->id, 'profile_id' => $profileId, 'scenario' => 'nominal_event', 'key' => (string) Str::uuid()],
            ['user_id' => $secondUser->id, 'profile_id' => $profileId, 'scenario' => 'duplicate_event', 'key' => (string) Str::uuid()],
        ];
        $outcomes = $this->runConcurrently($inputs);
        sort($outcomes);
        $this->assertSame(['ACCEPTED', 'CAPACITY'], $outcomes, 'Un seul emplacement global : une acceptation, une refus pour capacité.');
        $this->assertSame(1, DB::table('comparison_runs')->count());
        $this->assertSame(2, DB::table('lab_runs')->whereNotNull('comparison_id')->count());
    }

    public function test_two_concurrent_posts_with_same_key_create_one_comparison_and_two_children(): void
    {
        config(['lab.enabled' => true]);
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $this->definition($version);
        $key = (string) Str::uuid();
        $inputs = [
            ['user_id' => $user->id, 'profile_id' => $profileId, 'scenario' => 'nominal_event', 'key' => $key],
            ['user_id' => $user->id, 'profile_id' => $profileId, 'scenario' => 'nominal_event', 'key' => $key],
        ];
        $outcomes = $this->runConcurrently($inputs);
        $this->assertSame(['ACCEPTED', 'ACCEPTED'], $outcomes, 'Même clé, même charge : les deux processus voient le même rejeu.');
        $this->assertSame(1, DB::table('comparison_runs')->count());
        $this->assertSame(2, DB::table('lab_runs')->whereNotNull('comparison_id')->count(), 'Deux enfants = deux unités consommées, sans doublon au rejeu.');
        $this->assertSame(1, DB::table('api_idempotency')->count());
    }

    public function test_isolated_run_and_comparison_in_parallel_resolve_to_one_accepted(): void
    {
        config(['lab.enabled' => true]);
        $firstUser = User::factory()->verified()->create();
        $secondUser = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $this->definition($version);

        $env = $this->env();
        $simpleInput = ['user_id' => $firstUser->id, 'version_id' => $version->id, 'scenario' => 'nominal_event', 'key' => (string) Str::uuid()];
        $comparisonInput = ['user_id' => $secondUser->id, 'profile_id' => $profileId, 'scenario' => 'duplicate_event', 'key' => (string) Str::uuid()];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        $scripts = [
            base_path('tests/Fixtures/start-lab-run-concurrently.php'),
            base_path('tests/Fixtures/start-comparison-concurrently.php'),
        ];
        try {
            foreach ($streams as $index => $stream) {
                $process = new Process([PHP_BINARY, $scripts[$index]], base_path(), $env, $stream, 30);
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
            DB::beginTransaction();
            DB::select('SELECT pg_advisory_xact_lock(554478)');
            $streams[0]->write(json_encode($simpleInput, JSON_THROW_ON_ERROR)."\n");
            $streams[0]->close();
            $streams[1]->write(json_encode($comparisonInput, JSON_THROW_ON_ERROR)."\n");
            $streams[1]->close();
            $this->waitForWaiters($processes, 1);
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }
            sort($outcomes);
            $this->assertSame(['ACCEPTED', 'CAPACITY'], $outcomes, 'Un run isolé et une comparaison partagent le même emplacement.');
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    /**
     * @param  list<array<string, string>>  $inputs
     * @return list<string>
     */
    private function runConcurrently(array $inputs): array
    {
        $env = $this->env();
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/start-comparison-concurrently.php')], base_path(), $env, $stream, 30);
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
            DB::beginTransaction();
            DB::select('SELECT pg_advisory_xact_lock(554478)');
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode($inputs[$index], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            $this->waitForWaiters($processes, 1);
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }

            return $outcomes;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    /** @param list<Process> $processes */
    private function waitForWaiters(array $processes, int $expected): void
    {
        $deadline = microtime(true) + 10;
        do {
            foreach ($processes as $process) {
                $process->isRunning();
                $process->checkTimeout();
            }
            DB::statement('SELECT pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b34_worker' AND wait_event_type IN ('Lock', 'LWLock')")->total;
            if ($waiting >= $expected) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->assertGreaterThanOrEqual($expected, $waiting, 'Au moins un processus doit attendre le verrou avisé.');
    }

    /** @return array<string, string> */
    private function env(): array
    {
        $db = config('database.connections.pgsql');

        return [
            'APP_KEY' => (string) config('app.key'),
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'],
            'LAB_ENABLED' => 'true',
        ];
    }
}
