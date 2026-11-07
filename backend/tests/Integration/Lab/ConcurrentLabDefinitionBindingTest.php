<?php

declare(strict_types=1);

namespace Tests\Integration\Lab;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\Mdev44\DomainTables;

final class ConcurrentLabDefinitionBindingTest extends PostgresTestCase
{
    use DatabaseMigrations, RegistryFixtures;

    protected function tearDown(): void
    {
        try {
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_processes_actually_wait_on_locks_and_bind_once_without_creating_a_run(): void
    {
        $version = $this->publishedVersion(); // Committed before either child starts.
        $environment = $this->childEnvironment($version->id);
        $streams = [new InputStream, new InputStream];
        $processes = [];
        $database = DB::connection('pgsql');
        $database->beginTransaction();
        $database->table('capsule_versions')->where('id', $version->id)->lockForUpdate()->first();
        try {
            foreach ($streams as $stream) {
                $fixture = base_path('tests/Fixtures/bind-lab-definition-concurrently.php');
                // Use this frozen checkout and the guarded database configuration.
                // A local review wrapper must never replace the child's database.
                $child = new Process([PHP_BINARY, $fixture], base_path(), $environment, $stream, 40);
                $child->start();
                $processes[] = $child;
            }
            $pids = [];
            foreach ($processes as $child) {
                while (! str_contains($child->getOutput(), 'READY ') && $child->isRunning()) {
                    $child->checkTimeout();
                    usleep(10_000);
                }
                if (preg_match('/READY (\d+)/', $child->getOutput(), $matches) !== 1) {
                    $this->fail('Le processus de liaison doit annoncer son PID PostgreSQL.');
                }
                $this->assertCount(2, $matches);
                $pids[] = (int) $matches[1]; // PostgreSQL pid parsed from a digits-only protocol.
            }
            foreach ($streams as $stream) {
                $stream->write("GO\n");
                $stream->close();
            }
            $deadline = microtime(true) + 15;
            do {
                foreach ($processes as $child) {
                    $child->checkTimeout();
                    $child->getIncrementalOutput();
                    $child->getIncrementalErrorOutput();
                }
                $database->selectOne('SELECT pg_stat_clear_snapshot()');
                $waiting = $database->table('pg_stat_activity')->whereIn('pid', $pids)->where('wait_event_type', 'Lock')->count();
                if ($waiting === 2) {
                    break;
                }
                usleep(10_000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Deux connexions doivent réellement attendre les verrous, pas deux appels séquentiels.');
            $database->rollBack();
            do {
                $running = false;
                foreach ($processes as $child) {
                    if ($child->isRunning()) {
                        $child->checkTimeout();
                        $child->getIncrementalOutput();
                        $child->getIncrementalErrorOutput();
                        $running = true;
                    }
                }
                usleep(10_000);
            } while ($running);
            foreach ($processes as $child) {
                $this->assertSame(0, $child->getExitCode());
                $this->assertStringContainsString('DONE 0', $child->getOutput());
            }
            $this->assertSame(1, $database->table('lab_definitions')->count());
            $this->assertSame(1, $database->table('content_revisions')->where('action', 'lab.definition.bound')->count());
            $this->assertSame(0, $database->table('lab_runs')->count());
            $this->assertSame(0, $database->table('lab_results')->count());
        } finally {
            if ($database->transactionLevel() > 0) {
                $database->rollBack();
            }
            foreach ($processes as $child) {
                if ($child->isRunning()) {
                    $child->stop(1);
                }
            }
        }
    }

    /** @return array<string, string> */
    private function childEnvironment(string $versionId): array
    {
        $configuration = config('database.connections.pgsql');
        if (! is_array($configuration)) {
            throw new LogicException('Configuration PostgreSQL de test absente.');
        }
        $environment = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'B33_BIND_VERSION' => $versionId];
        foreach (['host', 'port', 'database', 'username', 'password'] as $field) {
            $value = $configuration[$field] ?? null;
            if (! is_string($value) && ! is_int($value)) {
                throw new LogicException('Paramètre PostgreSQL de test absent.');
            }
            $environment['DB_'.strtoupper($field)] = (string) $value; // Validated configuration serialized for the child, never printed.
        }

        return $environment;
    }
}
