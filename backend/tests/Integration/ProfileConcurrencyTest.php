<?php

namespace Tests\Integration;

use App\Models\Profile;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;

final class ProfileConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_two_blocked_writers_do_not_mix_profile_and_technologies_or_lose_a_version(): void
    {
        $user = User::factory()->verified()->create();
        Profile::factory()->for($user)->create(['bio' => 'Original']);
        $choices = [Technology::factory()->count(8)->create()->modelKeys(), Technology::factory()->count(8)->create()->modelKeys()];
        $db = config('database.connections.pgsql');
        $env = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password']];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/update-profile-concurrently.php')], base_path(), $env, $stream, 30);
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
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode(['user_id' => $user->id, 'bio' => 'Profil '.$index, 'technologies' => $choices[$index]], JSON_THROW_ON_ERROR)."\n");
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
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b10_worker' AND wait_event_type = 'Lock'")->total;
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
            $winner = array_search('UPDATED', $outcomes, true);
            $this->assertNotFalse($winner);
            $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'bio' => 'Profil '.$winner, 'lock_version' => 1]);
            $this->assertEqualsCanonicalizing($choices[$winner], $user->technologies()->pluck('technologies.id')->all());
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
