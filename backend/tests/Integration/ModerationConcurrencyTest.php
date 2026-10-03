<?php

namespace Tests\Integration;

use App\Data\Moderation\ReportData;
use App\Models\Profile;
use App\Models\User;
use App\Services\Moderation\CreateReportService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;

final class ModerationConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    /** @return iterable<string, array{string}> */
    public static function payloadCases(): iterable
    {
        yield 'duplicate reports' => ['duplicate'];
        yield 'atomic quota' => ['quota'];
        yield 'concurrent decisions' => ['decision'];
    }

    #[DataProvider('payloadCases')]
    public function test_reports_and_decisions_are_serialized(string $mode): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key]);
        $user = User::factory()->verified()->moderator()->create();
        $target = User::factory()->verified()->create();
        Profile::factory()->for($target)->create();
        $ids = [$target->id, $target->id];
        if ($mode === 'quota') {
            for ($index = 0; $index < 5; $index++) {
                $extra = User::factory()->verified()->create();
                Profile::factory()->for($extra)->create();
                if ($index < 4) {
                    app(CreateReportService::class)->create($user, new ReportData($extra->id, 'other', 'Signalement de test de quota uniquement.'));
                } else {
                    $ids[1] = $extra->id;
                }
            }
        }
        if ($mode === 'decision') {
            $report = app(CreateReportService::class)->create($user, new ReportData($target->id, 'other', 'Signalement fictif pour décision simultanée.'));
            $ids = [$report->id, $report->id];
        }
        $db = config('database.connections.pgsql');
        $env = ['APP_KEY' => $key, 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password']];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/moderate-concurrently.php')], base_path(), $env, $stream, 30);
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
                $stream->write(json_encode(['user_id' => $user->id, 'target_id' => $ids[$index], 'mode' => $mode], JSON_THROW_ON_ERROR)."\n");
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
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b30_worker' AND wait_event_type = 'Lock'")->total;
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
            $this->assertDatabaseCount('reports', $mode === 'quota' ? 5 : 1);
            $this->assertDatabaseCount('report_decisions', $mode === 'decision' ? 1 : 0);
            $this->assertDatabaseCount('notification_outbox', $mode === 'decision' ? 1 : 0);

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
