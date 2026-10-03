<?php

namespace Tests\Integration;

use App\Enums\Identity\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;

final class AdministrationConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_two_admins_cannot_both_remove_the_last_admin_access(): void
    {
        $admins = User::factory()->verified()->count(2)->create(['role' => Role::Admin]);
        $db = config('database.connections.pgsql');
        $env = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password']];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/administer-concurrently.php')], base_path(), $env, $stream, 30);
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
            DB::table('administration_guard')->where('id', 1)->lockForUpdate()->sole();
            foreach ($streams as $index => $stream) {
                $admin = $admins->get($index);
                $this->assertInstanceOf(User::class, $admin);
                $stream->write(json_encode(['user_id' => $admin->id], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            $deadline = microtime(true) + 10;
            do {
                foreach ($processes as $process) {
                    // Process doit pomper ses pipes pour transmettre les entrées de la barrière.
                    $process->isRunning();
                    $process->checkTimeout();
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b32_worker' AND wait_event_type = 'Lock'")->total;
                if ($waiting === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Les deux processus doivent être réellement en attente de verrou.');
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }
            $this->assertEqualsCanonicalizing(['UPDATED', 'CONFLICT'], $outcomes);
            $this->assertSame(1, User::where('role', Role::Admin)->count());
            $this->assertDatabaseCount('account_decisions', 1);
            $this->assertDatabaseCount('content_revisions', 1);

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
