<?php

namespace Tests\Integration\Collaboration;

use App\Enums\Identity\AccountStatus;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\ProposalRevision;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\TestDatabaseGuard;

final class ProposalsConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    private const string KEY = 'b1800000-3333-4444-8888-123456789abc';

    private const string OTHER_KEY = 'b1800000-3333-4444-8888-123456789abd';

    private const string EDITED_FIX = 'Correctif fictif clarifié après deux appels simultanés.';

    /** @return iterable<string, array{string}> */
    public static function races(): iterable
    {
        foreach (['create-distinct', 'create-replay', 'edit-version', 'edit-replay',
            'create-suspension', 'edit-suspension', 'create-parent-hidden', 'edit-parent-hidden',
            'create-parent-archived', 'edit-parent-archived'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('races')]
    public function test_two_processes_recheck_versions_and_visibility_after_the_lock(string $case): void
    {
        $actor = User::factory()->verified()->create();
        $other = $case === 'create-distinct' ? User::factory()->verified()->create() : $actor;
        $owner = User::factory()->verified()->create();
        foreach ([$actor, $other, $owner] as $user) {
            $this->assertSame(AccountStatus::Active, $user->refresh()->status);
            $this->assertTrue($user->hasVerifiedEmail());
        }
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open', 'lock_version' => 1]);
        $this->assertSame($owner->id, $parent->author_id);
        $this->assertSame('open', $parent->state->value);
        $this->assertNull($parent->hidden_at);
        $create = str_starts_with($case, 'create-');
        $code = "    // Code initial fictif et inerte.\n    <script>aucune exécution</script>\n";
        $proposal = $create ? null : Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $actor->id,
            'state' => 'proposed', 'lock_version' => 1, 'fix' => 'Correctif initial entièrement fictif.', 'code' => $code]);
        $original = $proposal?->only(['diagnosis', 'fix', 'verification', 'limits', 'code']) ?? [];
        $database = config('database.connections.pgsql');
        TestDatabaseGuard::check(config('app.env'), config('database.default'), $database);
        $environment = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
            'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => $database['database'], 'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => (string) ($database['password'] ?? '')];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/proposal-concurrently.php')], base_path(), $environment, $stream, 45);
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
            User::whereIn('id', [$actor->id, $other->id, $owner->id])->orderBy('id')->lockForUpdate()->get();
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode(['actor_id' => $index === 0 ? $actor->id : $other->id, 'parent_id' => $parent->id,
                    'proposal_id' => $proposal?->id, 'create' => $create,
                    'key' => $case === 'edit-version' && $index === 1 ? self::OTHER_KEY : self::KEY], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            $deadline = microtime(true) + 15;
            do {
                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $this->fail('Le processus B18 a terminé avant l’attente du verrou : '.$process->getErrorOutput());
                    }
                    $process->checkTimeout();
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b18_worker' AND wait_event_type = 'Lock'")->total;
                if ($waiting === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Les deux processus PHP doivent attendre un verrou PostgreSQL.');
            match ($case) {
                'create-suspension', 'edit-suspension' => $actor->forceFill(['status' => 'suspended'])->save(),
                'create-parent-hidden', 'edit-parent-hidden' => $parent->forceFill(['hidden_at' => now()])->save(),
                'create-parent-archived', 'edit-parent-archived' => $parent->forceFill(['state' => 'archived'])->save(),
                'create-distinct', 'create-replay', 'edit-version', 'edit-replay' => null,
                default => throw new \InvalidArgumentException('Fixture de course B18 inconnue.'),
            };
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }
            $expected = match ($case) {
                'create-distinct', 'create-replay', 'edit-replay' => ['APPLIED', 'APPLIED'],
                'edit-version' => ['APPLIED', 'CONFLICT'],
                default => ['FORBIDDEN', 'FORBIDDEN'],
            };
            $this->assertEqualsCanonicalizing($expected, $outcomes);
            $applied = in_array('APPLIED', $expected, true);
            $created = $create && $applied ? ($case === 'create-distinct' ? 2 : 1) : 0;
            $mutations = $create ? $created : ($applied ? 1 : 0);
            $transition = $created > 0 ? 1 : 0;
            $this->assertDatabaseCount('proposals', $create ? $created : 1);
            $this->assertDatabaseCount('api_idempotency', $mutations);
            $this->assertDatabaseCount('proposal_revisions', $create ? $created : ($applied ? 2 : 0));
            $this->assertDatabaseCount('content_revisions', $mutations + $transition);
            $this->assertDatabaseCount('notification_outbox', $created);
            $this->assertDatabaseCount('internal_notifications', 0);
            $this->assertSame($transition, DB::table('content_revisions')->where('resource_id', $parent->id)->where('action', 'help_request.started')->count());
            $this->assertSame($created, DB::table('content_revisions')->where('action', 'proposal.created')->count());
            $this->assertSame(! $create && $applied ? 1 : 0, DB::table('content_revisions')->where('action', 'proposal.updated')->count());
            $parent->refresh();
            $this->assertSame($transition ? 2 : 1, $parent->lock_version);
            $this->assertSame(str_ends_with($case, 'parent-archived') ? 'archived' : ($transition ? 'in_progress' : 'open'), $parent->state->value);
            $this->assertSame(str_ends_with($case, 'parent-hidden'), $parent->hidden_at !== null);
            if ($create && $applied) {
                $proposals = Proposal::where('request_id', $parent->id)->orderBy('id')->get();
                $this->assertEqualsCanonicalizing($case === 'create-distinct' ? [$actor->id, $other->id] : [$actor->id], $proposals->pluck('author_id')->all());
                foreach ($proposals as $current) {
                    $this->assertSame(1, $current->lock_version);
                    $this->assertSame('proposed', $current->state->value);
                    $this->assertSame([1], ProposalRevision::where('proposal_id', $current->id)->pluck('proposal_version')->all());
                    $this->assertDatabaseHas('notification_outbox', ['event_id' => $current->id, 'recipient_id' => $owner->id, 'kind' => 'proposal.created']);
                }
                $started = DB::table('content_revisions')->where('resource_id', $parent->id)->where('action', 'help_request.started')->sole();
                $metadata = json_decode($started->metadata, true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(2, $metadata['request_version']);
                $this->assertContains($metadata['proposal_id'], $proposals->pluck('id')->all());
                $this->assertContains($started->actor_id, $proposals->pluck('author_id')->all());
            } elseif ($proposal !== null) {
                $proposal->refresh();
                $this->assertSame($applied ? 2 : 1, $proposal->lock_version);
                $this->assertSame($applied, $proposal->edited_at !== null);
                $this->assertSame(array_replace($original, $applied ? ['fix' => self::EDITED_FIX] : []), $proposal->only(['diagnosis', 'fix', 'verification', 'limits', 'code']));
                if ($applied) {
                    $history = ProposalRevision::where('proposal_id', $proposal->id)->orderBy('proposal_version')->get();
                    $this->assertSame([1, 2], $history->pluck('proposal_version')->all());
                    $this->assertSame(['before_edit', 'updated'], $history->pluck('action')->all());
                    $this->assertSame([$code, $code], $history->pluck('code')->all());
                }
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
            TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
            $ids = Proposal::where('request_id', $parent->id)->pluck('id');
            $editRoutes = [];
            foreach ($ids as $id) {
                $editRoutes[] = 'PATCH /api/v1/proposals/'.$id;
            }
            DB::table('notification_outbox')->whereIn('event_id', $ids)->delete();
            DB::table('internal_notifications')->whereIn('event_id', $ids)->delete();
            DB::table('api_idempotency')->whereIn('user_id', [$actor->id, $other->id])->where(function (Builder $query) use ($parent, $editRoutes): void {
                $query->where('route_target', 'POST /api/v1/requests/'.$parent->id.'/proposals')
                    ->orWhereIn('route_target', $editRoutes);
            })->delete();
            DB::table('content_revisions')->where('resource_type', 'proposal')->whereIn('resource_id', $ids)->delete();
            DB::table('content_revisions')->where('resource_type', 'help_request')->where('resource_id', $parent->id)->delete();
            ProposalRevision::whereIn('proposal_id', $ids)->delete();
            Proposal::where('request_id', $parent->id)->delete();
            $parent->delete();
        }
    }
}
