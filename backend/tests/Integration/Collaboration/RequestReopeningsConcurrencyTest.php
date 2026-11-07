<?php

namespace Tests\Integration\Collaboration;

use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\RequestReopeningFixtures;
use Tests\Support\TestDatabaseGuard;

final class RequestReopeningsConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations, RequestReopeningFixtures;

    private const string KEY = 'b2000000-3333-4444-8888-123456789abc';

    private const string OTHER_KEY = 'b2000000-3333-4444-8888-123456789abd';

    /** @return iterable<string, array{string}> */
    public static function races(): iterable
    {
        foreach (['reopen-distinct', 'reopen-replay', 'reopen-before-resolve', 'resolve-before-reopen', 'owner-suspended', 'parent-hidden', 'former-contributor-suspended'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('races')]
    public function test_real_workers_share_lock_order_and_recheck_state_after_lock_wait(string $case): void
    {
        $owner = User::factory()->verified()->create();
        $author = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'resolved', 'lock_version' => 4]);
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $author->id, 'state' => 'accepted', 'lock_version' => 7]);
        $original = $proposal->refresh()->getAttributes();
        $resolution = Resolution::factory()->create(['request_id' => $parent->id, 'proposal_id' => $proposal->id, 'accepted_by' => $owner->id,
            'proposal_version' => 7, 'environment' => 'Contexte fictif original.', 'accepted_at' => now()->subMinute()]);
        $other = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $author->id, 'state' => 'proposed', 'lock_version' => 1]);
        $base = ['actor_id' => $owner->id, 'parent_id' => $parent->id, 'proposal_id' => $other->id];
        $reopen = [...$base, 'operation' => 'reopen', 'lock_version' => 4, 'key' => self::KEY];
        $resolve = [...$base, 'operation' => 'resolve', 'lock_version' => 5, 'key' => self::OTHER_KEY];
        $packets = match ($case) {
            'reopen-before-resolve' => [$reopen, $resolve],
            'resolve-before-reopen' => [$resolve, $reopen],
            'reopen-replay' => [$reopen, $reopen],
            default => [$reopen, [...$reopen, 'key' => self::OTHER_KEY]],
        };
        $database = config('database.connections.pgsql');
        TestDatabaseGuard::check(config('app.env'), config('database.default'), $database);
        $environment = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
            'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => $database['database'], 'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => (string) ($database['password'] ?? '')];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/reopening-concurrently.php')], base_path(), $environment, $stream, 45);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                while ($process->isRunning() && ! str_contains($process->getOutput(), 'READY')) {
                    $process->checkTimeout();
                    usleep(10000);
                }
                $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
            }
            DB::beginTransaction();
            $ordered = in_array($case, ['reopen-before-resolve', 'resolve-before-reopen'], true);
            if ($ordered) {
                HelpRequest::whereKey($parent->id)->lockForUpdate()->firstOrFail();
                $this->send($streams[0], $packets[0]);
                $this->waitForLocks($processes, 1);
                $firstQuery = DB::scalar("SELECT query FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b20_worker' AND wait_event_type = 'Lock'");
                $this->assertIsString($firstQuery);
                $this->assertStringContainsString('help_requests', $firstQuery);
                $this->send($streams[1], $packets[1]);
            } else {
                User::whereIn('id', [$owner->id, $author->id])->orderBy('id')->lockForUpdate()->get();
                foreach ($streams as $index => $stream) {
                    $this->send($stream, $packets[$index]);
                }
            }
            $this->waitForLocks($processes, 2);
            match ($case) {
                'owner-suspended' => $owner->forceFill(['status' => 'suspended'])->save(),
                'parent-hidden' => $parent->forceFill(['hidden_at' => now()])->save(),
                'former-contributor-suspended' => $author->forceFill(['status' => 'suspended'])->save(),
                default => null,
            };
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $result = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
                $this->assertIsArray($result);
                $results[] = $result;
            }
            $statuses = array_column($results, 'status');
            $closed = in_array($case, ['owner-suspended', 'parent-hidden'], true);
            if ($closed) {
                foreach ($statuses as $status) {
                    $this->assertContains($status, ['FORBIDDEN', 'INVISIBLE']);
                }
            } elseif ($case === 'reopen-replay') {
                $this->assertSame(['APPLIED', 'APPLIED'], $statuses);
                $this->assertSame($results[0]['reopening_id'], $results[1]['reopening_id']);
            } elseif ($case === 'reopen-before-resolve') {
                $this->assertSame(['APPLIED', 'APPLIED'], $statuses);
            } elseif ($case === 'resolve-before-reopen') {
                $this->assertSame(['CONFLICT', 'APPLIED'], $statuses);
            } else {
                $this->assertEqualsCanonicalizing(['APPLIED', 'CONFLICT'], $statuses);
            }
            $resolvedAgain = $case === 'reopen-before-resolve';
            $this->assertDatabaseCount('request_reopenings', $closed ? 0 : 1);
            $this->assertSame($closed ? 'resolved' : ($resolvedAgain ? 'resolved' : 'open'), $parent->refresh()->state->value);
            $this->assertSame($closed ? 4 : ($resolvedAgain ? 6 : 5), $parent->lock_version);
            $this->assertSame(! $closed, $resolution->refresh()->revoked_at !== null);
            $this->assertSame($original, $proposal->refresh()->getAttributes());
            $this->assertSame($resolvedAgain ? 1 : ($closed ? 1 : 0), DB::table('resolutions')->where('request_id', $parent->id)->whereNull('revoked_at')->count());
            $this->assertDatabaseCount('api_idempotency', $closed ? 0 : ($resolvedAgain ? 2 : 1));
            $this->assertDatabaseCount('content_revisions', $closed ? 0 : ($resolvedAgain ? 4 : 2));
            $this->assertSame($closed ? 0 : 1, DB::table('notification_outbox')->where('kind', 'request.reopened')->count());
            $this->assertDatabaseCount('internal_notifications', 0);
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
            $this->removeReopeningFixtures([$parent->id]);
        }
    }

    /** @param array<string, mixed> $packet */
    private function send(InputStream $stream, array $packet): void
    {
        $stream->write(json_encode($packet, JSON_THROW_ON_ERROR)."\n");
        $stream->close();
    }

    /** @param list<Process> $processes */
    private function waitForLocks(array $processes, int $expected): void
    {
        $deadline = microtime(true) + 15;
        $waiting = 0;
        do {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail('Un processus B20 a quitté avant le verrou : '.$process->getErrorOutput());
                }
                $process->checkTimeout();
            }
            DB::statement('SELECT pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b20_worker' AND wait_event_type = 'Lock'")->total;
            if ($waiting === $expected) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->assertSame($expected, $waiting, 'Les deux processus doivent attendre de vrais verrous PostgreSQL.');
    }
}
