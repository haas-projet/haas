<?php

namespace Tests\Integration;

use App\Data\Audit\ProfileRevisionData;
use App\Models\Profile;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;

final class AuditConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_writer_requires_an_existing_business_transaction(): void
    {
        $user = User::factory()->verified()->create();
        $profile = Profile::factory()->for($user)->create();
        $this->assertSame(0, DB::transactionLevel());
        $this->expectException(\LogicException::class);
        app(AuditWriter::class)->profileUpdated($user, $profile, new ProfileRevisionData(['bio']));
    }

    public function test_two_serialized_business_writes_create_distinct_ordered_revisions(): void
    {
        $user = User::factory()->verified()->create();
        Profile::factory()->for($user)->create(['bio' => 'Original']);
        $db = config('database.connections.pgsql');
        $env = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password']];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/update-profile-with-audit-concurrently.php')], base_path(), $env, $stream, 30);
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
                $stream->write(json_encode(['user_id' => $user->id, 'bio' => 'Profil '.$index], JSON_THROW_ON_ERROR)."\n");
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
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b12_worker' AND wait_event_type = 'Lock'")->total;
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
            $this->assertSame(['UPDATED', 'UPDATED'], $outcomes);
            $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'lock_version' => 2]);
            $rows = DB::table('content_revisions')->orderBy('revision')->get();
            $this->assertSame([1, 2], $rows->pluck('revision')->all());
            $this->assertSame([$user->id, $user->id], $rows->pluck('actor_id')->all());
            $this->assertSame([1, 2], $rows->map(fn ($row): int => json_decode($row->metadata, true, flags: JSON_THROW_ON_ERROR)['profile_version'])->all());

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
