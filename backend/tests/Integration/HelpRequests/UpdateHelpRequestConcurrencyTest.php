<?php

namespace Tests\Integration\HelpRequests;

use App\Models\Comment;
use App\Models\HelpRequest;
use App\Models\HelpRequestRevision;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;

final class UpdateHelpRequestConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    /** @return iterable<string, array{string}> */
    public static function raceCases(): iterable
    {
        foreach (['versions', 'replay', 'publish-edit', 'note-before-lock', 'suspension', 'hidden'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('raceCases')]
    public function test_edits_are_atomic_and_recheck_current_state_under_real_contention(string $case): void
    {
        $user = User::factory()->verified()->create();
        $request = HelpRequest::factory()->for($user, 'author')->create(['state' => $case === 'note-before-lock' ? 'open' : 'draft']);
        $request->technologies()->attach(Technology::factory()->create());
        $db = config('database.connections.pgsql');
        $env = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'], 'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password']];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/update-help-request-concurrently.php')], base_path(), $env, $stream, 30);
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
                $stream->write(json_encode(['user_id' => $user->id, 'request_id' => $request->id, 'publish' => $case === 'publish-edit' && $index === 1, 'key' => $case === 'replay' || $index === 0 ? 'fda9a000-3333-4444-8888-123456789abc' : 'fda9a000-3333-4444-8888-123456789abd'], JSON_THROW_ON_ERROR)."\n");
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
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b16_worker' AND wait_event_type = 'Lock'")->total;
                if ($waiting === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Les deux processus doivent être réellement en attente de verrou.');
            if ($case === 'note-before-lock') {
                Comment::factory()->create(['request_id' => $request->id]);
            } elseif ($case === 'suspension') {
                $user->forceFill(['status' => 'suspended'])->save();
            } elseif ($case === 'hidden') {
                $request->forceFill(['hidden_at' => now()])->save();
            }
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }
            $expected = match ($case) {
                'replay' => ['APPLIED', 'APPLIED'],
                'note-before-lock' => ['INVALID', 'INVALID'],
                'suspension', 'hidden' => ['FORBIDDEN', 'FORBIDDEN'],
                default => ['APPLIED', 'CONFLICT'],
            };
            $this->assertEqualsCanonicalizing($expected, $outcomes);
            $changed = in_array('APPLIED', $expected, true);
            $this->assertSame($changed ? 2 : 1, $request->refresh()->lock_version);
            $this->assertDatabaseCount('api_idempotency', $changed ? 1 : 0);
            $this->assertDatabaseCount('content_revisions', $changed ? 1 : 0);
            $this->assertDatabaseCount('help_request_revisions', $changed ? 1 : 0);
            if ($changed) {
                $this->assertSame(2, HelpRequestRevision::sole()->request_version);
                $this->assertSame($request->state->value === 'open' ? 'published' : 'updated', HelpRequestRevision::sole()->action);
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
            DB::table('comments')->where('request_id', $request->id)->delete();
            DB::table('help_requests')->where('author_id', $user->id)->delete();
        }
    }
}
