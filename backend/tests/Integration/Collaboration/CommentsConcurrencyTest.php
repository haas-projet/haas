<?php

namespace Tests\Integration\Collaboration;

use App\Models\Comment;
use App\Models\CommentRevision;
use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;

final class CommentsConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    /** @return iterable<string, array{string}> */
    public static function races(): iterable
    {
        foreach (['create-replay', 'edit-version', 'edit-replay', 'suspension', 'parent-hidden', 'parent-archived', 'comment-hidden'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('races')]
    public function test_two_processes_recheck_versions_and_visibility_after_the_lock(string $case): void
    {
        $actor = User::factory()->verified()->create();
        $owner = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open']);
        $create = $case === 'create-replay';
        $comment = $create ? null : Comment::factory()->create(['request_id' => $parent->id, 'author_id' => $actor->id, 'body' => 'Commentaire fictif initial']);
        $database = config('database.connections.pgsql');
        $environment = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'], 'DB_DATABASE' => $database['database'], 'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password']];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/comment-concurrently.php')], base_path(), $environment, $stream, 45);
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
            User::whereIn('id', [$actor->id, $owner->id])->orderBy('id')->lockForUpdate()->get();
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode(['actor_id' => $actor->id, 'parent_id' => $parent->id, 'comment_id' => $comment?->id, 'create' => $create,
                    'key' => $case !== 'edit-version' || $index === 0 ? 'b1700000-3333-4444-8888-123456789abc' : 'b1700000-3333-4444-8888-123456789abd'], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            $deadline = microtime(true) + 15;
            do {
                foreach ($processes as $process) {
                    $process->isRunning();
                    $process->checkTimeout();
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b17_worker' AND wait_event_type = 'Lock'")->total;
                if ($waiting === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Les deux processus PHP doivent attendre un verrou PostgreSQL.');
            match ($case) {
                'suspension' => $actor->forceFill(['status' => 'suspended'])->save(),
                'parent-hidden' => $parent->forceFill(['hidden_at' => now()])->save(),
                'parent-archived' => $parent->forceFill(['state' => 'archived'])->save(),
                'comment-hidden' => $comment?->forceFill(['hidden_at' => now()])->save(),
                'create-replay', 'edit-version', 'edit-replay' => null,
                default => throw new \InvalidArgumentException('Fixture de course B17 inconnue.'),
            };
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }
            $expected = match ($case) {
                'create-replay', 'edit-replay' => ['APPLIED', 'APPLIED'],
                'edit-version' => ['APPLIED', 'CONFLICT'],
                default => ['FORBIDDEN', 'FORBIDDEN'],
            };
            $this->assertEqualsCanonicalizing($expected, $outcomes);
            $applied = in_array('APPLIED', $expected, true);
            $this->assertDatabaseCount('comments', 1);
            $this->assertDatabaseCount('api_idempotency', $applied ? 1 : 0);
            $this->assertDatabaseCount('content_revisions', $applied ? 1 : 0);
            $this->assertDatabaseCount('comment_revisions', $applied ? ($create ? 1 : 2) : 0);
            $this->assertDatabaseCount('notification_outbox', $create ? 1 : 0);
            $this->assertSame($applied && ! $create ? 2 : 1, Comment::sole()->lock_version);
            $this->assertSame(1, $parent->refresh()->lock_version);
            $this->assertSame($case === 'parent-archived' ? 'archived' : 'open', $parent->state->value);
            if ($applied && ! $create) {
                $this->assertSame([1, 2], CommentRevision::orderBy('comment_version')->pluck('comment_version')->all());
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
            $ids = Comment::where('request_id', $parent->id)->pluck('id');
            DB::table('notification_outbox')->whereIn('event_id', $ids)->delete();
            DB::table('internal_notifications')->whereIn('event_id', $ids)->delete();
            Comment::where('request_id', $parent->id)->delete();
            $parent->delete();
        }
    }
}
