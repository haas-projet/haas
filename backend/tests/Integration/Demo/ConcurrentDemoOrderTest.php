<?php

namespace Tests\Integration\Demo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\DemoPostgresTestCase;
use Tests\Support\RefreshDemoDatabase;

final class ConcurrentDemoOrderTest extends DemoPostgresTestCase
{
    use RefreshDemoDatabase;

    #[DataProvider('concurrentBodies')]
    public function test_two_real_postgres_waiters_preserve_order_or_cache_bounds(int $secondAmount, string $mode): void
    {
        $configuration = config('database.connections.demo');
        config(['database.connections.demo_barrier' => $configuration]);
        $barrier = DB::connection('demo_barrier');
        $key = (string) Str::uuid();
        $streams = [new InputStream, new InputStream];
        $processes = [];
        if ($mode === 'cache') {
            for ($index = 0; $index < 999; $index++) {
                $barrier->table('cache')->insert(['key' => 'b38-race-base-'.$index, 'value' => 'i:1;', 'expiration' => time() + 60]);
            }
        }
        $barrier->beginTransaction();
        $barrier->select('SELECT pg_advisory_xact_lock('.($mode === 'cache' ? '238039' : '238038').')');
        try {
            foreach ($streams as $index => $stream) {
                $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/Fixtures/record-demo-order-concurrently.php'], dirname(__DIR__, 3), [
                    'DEMO_ENV' => 'testing', 'DEMO_DB_HOST' => (string) $configuration['host'], 'DEMO_DB_PORT' => (string) $configuration['port'],
                    'DEMO_DB_DATABASE' => (string) $configuration['database'], 'DEMO_DB_USERNAME' => (string) $configuration['username'], 'DEMO_DB_PASSWORD' => (string) $configuration['password'],
                    'DEMO_HAAS_DATABASE' => (string) config('demo.haas_database'), 'DEMO_CACHE_STORE' => 'database', 'DEMO_TEST_MODE' => $mode, 'DEMO_TEST_CACHE_KEY' => 'b38-race-child-'.$index,
                    'DEMO_IDEMPOTENCY_KEY' => (string) config('demo.idempotency_key'), 'DEMO_TEST_KEY' => $key, 'DEMO_TEST_AMOUNT' => (string) ($index === 0 ? 1299 : $secondAmount),
                    'APP_KEY' => false, 'APP_PREVIOUS_KEYS' => false, 'DB_PASSWORD' => false, 'MAIL_PASSWORD' => false,
                ], $stream, 30);
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
            foreach ($streams as $stream) {
                $stream->write("GO\n");
                $stream->close();
            }
            $waiting = 0;
            $deadline = microtime(true) + 15;
            do {
                foreach ($processes as $process) {
                    $process->getIncrementalOutput();
                    $process->getIncrementalErrorOutput();
                    $process->checkTimeout();
                }
                $row = $barrier->selectOne("SELECT count(*) AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'b38_concurrency_child' AND wait_event_type = 'Lock' AND wait_event = 'advisory'");
                $waiting = (int) $row->total;
                usleep(10000);
            } while ($waiting < 2 && microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Deux processus doivent attendre simultanément le verrou PostgreSQL.');
            $barrier->commit();
            while ($processes[0]->isRunning() || $processes[1]->isRunning()) {
                foreach ($processes as $process) {
                    $process->getIncrementalOutput();
                    $process->getIncrementalErrorOutput();
                    $process->checkTimeout();
                }
                usleep(10000);
            }
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
            }
            if ($mode === 'cache') {
                $statuses = [$results[0]['status'], $results[1]['status']];
                sort($statuses);
                $this->assertSame([200, 429], $statuses);
                $this->assertSame(1000, $barrier->table('cache')->count());
                $this->assertSame(0, $barrier->table('demo_orders')->count());

                return;
            }
            $this->assertSame(1, $barrier->table('demo_orders')->where('idempotency_key_hash', hash('sha256', $key))->count());
            if ($secondAmount === 1299) {
                $this->assertSame($results[0]['id'], $results[1]['id']);
                $replays = [$results[0]['replay'], $results[1]['replay']];
                sort($replays);
                $this->assertSame([false, true], $replays);
            } else {
                $statuses = [$results[0]['status'], $results[1]['status']];
                sort($statuses);
                $this->assertSame([200, 409], $statuses);
            }
        } finally {
            if ($barrier->transactionLevel() > 0) {
                $barrier->rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach ($streams as $stream) {
                $stream->close();
            }
            $barrier->table('demo_orders')->where('idempotency_key_hash', hash('sha256', $key))->delete();
            $barrier->table('cache')->where('key', 'like', 'b38-race-%')->delete();
            $barrier->table('cache')->where('key', 'like', 'haas-b2-b38-race-%')->delete();
            DB::purge('demo_barrier');
        }
    }

    /** @return iterable<string,array{int,string}> */
    public static function concurrentBodies(): iterable
    {
        yield 'rejeu' => [1299, 'order'];
        yield 'conflit' => [1300, 'order'];
        yield 'dernier emplacement cache' => [1299, 'cache'];
    }
}
