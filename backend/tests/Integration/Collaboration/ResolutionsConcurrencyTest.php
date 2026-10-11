<?php

namespace Tests\Integration\Collaboration;

use App\Data\Idempotency\IdempotencyKey;
use App\Enums\Identity\AccountStatus;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\ProposalRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\TestDatabaseGuard;

final class ResolutionsConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    private const string KEY = 'b1900000-3333-4444-8888-123456789abc';

    private const string OTHER_KEY = 'b1900000-3333-4444-8888-123456789abd';

    private const string EDITED_FIX = 'Correctif fictif clarifié avant une acceptation concurrente.';

    private const string ENVIRONMENT = 'Environnement local fictif PHP et PostgreSQL.';

    private const string NOTE = 'Observation fictive : cette proposition a résolu le blocage décrit.';

    /** @return iterable<string, array{string}> */
    public static function races(): iterable
    {
        foreach (['accept-distinct', 'accept-replay', 'edit-before-accept', 'accept-before-edit',
            'owner-suspension', 'contributor-suspension', 'parent-hidden', 'parent-archived', 'proposal-hidden'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('races')]
    public function test_acceptance_and_edits_recheck_current_state_after_real_postgresql_locks(string $case): void
    {
        $owner = User::factory()->verified()->create();
        $contributor = User::factory()->verified()->create();
        $otherContributor = User::factory()->verified()->create();
        foreach ([$owner, $contributor, $otherContributor] as $user) {
            $this->assertSame(AccountStatus::Active, $user->refresh()->status);
            $this->assertTrue($user->hasVerifiedEmail());
        }
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'in_progress', 'lock_version' => 1]);
        $this->assertSame($owner->id, $parent->author_id);
        $this->assertSame('in_progress', $parent->state->value);
        $this->assertNull($parent->hidden_at);
        $code = "    // Code fictif conservé octet pour octet.\n    <script>aucune exécution</script>\n";
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $contributor->id,
            'state' => 'proposed', 'lock_version' => 1, 'code' => $code]);
        $otherProposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $otherContributor->id,
            'state' => 'proposed', 'lock_version' => 1, 'code' => $code]);
        foreach ([$proposal, $otherProposal] as $current) {
            $this->assertSame($parent->id, $current->request_id);
            $this->assertSame('proposed', $current->state->value);
            $this->assertSame(1, $current->lock_version);
            $this->assertNull($current->hidden_at);
        }
        $this->assertSame($contributor->id, $proposal->author_id);
        $this->assertSame($otherContributor->id, $otherProposal->author_id);
        $fields = ['diagnosis', 'fix', 'verification', 'limits', 'code'];
        $original = $proposal->only($fields);
        $otherOriginal = $otherProposal->only($fields);
        $mixed = in_array($case, ['edit-before-accept', 'accept-before-edit'], true);
        $packets = [
            ['actor_id' => $owner->id, 'parent_id' => $parent->id, 'proposal_id' => $proposal->id, 'operation' => 'resolve', 'key' => self::KEY],
            ['actor_id' => $owner->id, 'parent_id' => $parent->id, 'proposal_id' => $case === 'accept-distinct' ? $otherProposal->id : $proposal->id,
                'operation' => 'resolve', 'key' => $case === 'accept-replay' ? self::KEY : self::OTHER_KEY],
        ];
        if ($mixed) {
            $edit = ['actor_id' => $contributor->id, 'parent_id' => $parent->id, 'proposal_id' => $proposal->id, 'operation' => 'edit', 'key' => self::OTHER_KEY];
            $packets = $case === 'edit-before-accept' ? [$edit, $packets[0]] : [$packets[0], $edit];
        }
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
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/resolution-concurrently.php')], base_path(), $environment, $stream, 45);
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
            if ($mixed) {
                // The first worker locks both accounts, then waits on this parent.
                // The second worker subsequently waits on a shared account: the winner is imposed.
                HelpRequest::whereKey($parent->id)->lockForUpdate()->firstOrFail();
                $this->send($streams[0], $packets[0]);
                $this->waitForLocks($processes, 1);
                $firstQuery = DB::scalar("SELECT query FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b19_worker' AND wait_event_type = 'Lock'");
                $this->assertIsString($firstQuery);
                $this->assertStringContainsString('help_requests', $firstQuery);
                $this->send($streams[1], $packets[1]);
            } else {
                User::whereIn('id', [$owner->id, $contributor->id, $otherContributor->id])->orderBy('id')->lockForUpdate()->get();
                HelpRequest::whereKey($parent->id)->lockForUpdate()->firstOrFail();
                Proposal::whereIn('id', [$proposal->id, $otherProposal->id])->orderBy('id')->lockForUpdate()->get();
                foreach ($streams as $index => $stream) {
                    $this->send($stream, $packets[$index]);
                }
            }
            $this->waitForLocks($processes, 2);
            match ($case) {
                'owner-suspension' => $owner->forceFill(['status' => 'suspended'])->save(),
                'contributor-suspension' => $contributor->forceFill(['status' => 'suspended'])->save(),
                'parent-hidden' => $parent->forceFill(['hidden_at' => now()])->save(),
                'parent-archived' => $parent->forceFill(['state' => 'archived'])->save(),
                'proposal-hidden' => $proposal->forceFill(['hidden_at' => now()])->save(),
                'accept-distinct', 'accept-replay', 'edit-before-accept', 'accept-before-edit' => null,
                default => throw new \InvalidArgumentException('Fixture de course B19 inconnue.'),
            };
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $result = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
                $this->assertIsArray($result);
                $this->assertIsString($result['status'] ?? null);
                $results[] = $result;
            }
            $statuses = array_column($results, 'status');
            if ($case === 'accept-distinct') {
                $this->assertEqualsCanonicalizing(['APPLIED', 'CONFLICT'], $statuses);
            } elseif ($case === 'accept-replay') {
                $this->assertSame(['APPLIED', 'APPLIED'], $statuses);
                $this->assertSame($results[0]['resolution_id'], $results[1]['resolution_id']);
            } elseif ($case === 'edit-before-accept') {
                $this->assertSame(['APPLIED', 'CONFLICT'], $statuses);
                $this->assertSame($proposal->id, $results[0]['proposal_id']);
            } elseif ($case === 'accept-before-edit') {
                $this->assertSame(['APPLIED', 'FORBIDDEN'], $statuses);
            } else {
                foreach ($statuses as $status) {
                    $this->assertContains($status, ['FORBIDDEN', 'INVISIBLE']);
                }
                $this->assertCount(2, $statuses);
            }
            $accepted = in_array($case, ['accept-distinct', 'accept-replay', 'accept-before-edit'], true);
            $edited = $case === 'edit-before-accept';
            $this->assertDatabaseCount('resolutions', $accepted ? 1 : 0);
            $this->assertSame($accepted ? 1 : 0, DB::table('resolutions')->where('request_id', $parent->id)->whereNull('revoked_at')->count());
            $this->assertDatabaseCount('api_idempotency', $accepted || $edited ? 1 : 0);
            $this->assertDatabaseCount('content_revisions', $accepted ? 2 : ($edited ? 1 : 0));
            $this->assertDatabaseCount('proposal_revisions', $edited ? 2 : 0);
            $this->assertDatabaseCount('notification_outbox', $accepted ? 1 : 0);
            $this->assertDatabaseCount('internal_notifications', 0);
            $this->assertSame($accepted ? 1 : 0, DB::table('content_revisions')->where('resource_id', $parent->id)->where('action', 'help_request.resolved')->count());
            $this->assertSame($accepted ? 1 : 0, DB::table('content_revisions')->where('action', 'resolution.accepted')->count());
            $this->assertSame($edited ? 1 : 0, DB::table('content_revisions')->where('action', 'proposal.updated')->count());
            $parent->refresh();
            $this->assertSame($accepted ? 2 : 1, $parent->lock_version);
            $this->assertSame($accepted ? 'resolved' : ($case === 'parent-archived' ? 'archived' : 'in_progress'), $parent->state->value);
            $this->assertSame($case === 'parent-hidden', $parent->hidden_at !== null);
            $proposal->refresh();
            $otherProposal->refresh();
            $this->assertSame($edited ? 2 : 1, $proposal->lock_version);
            $this->assertSame(1, $otherProposal->lock_version);
            $this->assertSame($edited, $proposal->edited_at !== null);
            $this->assertNull($otherProposal->edited_at);
            $this->assertSame($case === 'proposal-hidden', $proposal->hidden_at !== null);
            $this->assertNull($otherProposal->hidden_at);
            $this->assertSame(array_replace($original, $edited ? ['fix' => self::EDITED_FIX] : []), $proposal->only($fields));
            $this->assertSame($otherOriginal, $otherProposal->only($fields));
            if ($accepted) {
                $resolution = DB::table('resolutions')->where('request_id', $parent->id)->sole();
                $this->assertTrue(Str::isUuid($resolution->id));
                $this->assertSame($owner->id, $resolution->accepted_by);
                $this->assertSame(self::ENVIRONMENT, $resolution->environment);
                $this->assertSame(self::NOTE, $resolution->validation_note);
                $this->assertSame(1, $resolution->proposal_version);
                $this->assertNotNull($resolution->accepted_at);
                $this->assertNull($resolution->revoked_at);
                $this->assertContains($resolution->proposal_id, [$proposal->id, $otherProposal->id]);
                foreach ($results as $result) {
                    if ($result['status'] === 'APPLIED') {
                        $this->assertSame($resolution->id, $result['resolution_id']);
                    }
                }
                foreach ([$proposal, $otherProposal] as $current) {
                    $this->assertSame($current->id === $resolution->proposal_id ? 'accepted' : 'proposed', $current->state->value);
                }
                $recipient = $resolution->proposal_id === $proposal->id ? $contributor : $otherContributor;
                $this->assertDatabaseHas('notification_outbox', ['event_id' => $resolution->id, 'recipient_id' => $recipient->id, 'kind' => 'resolution.accepted']);
                $this->assertDatabaseHas('content_revisions', ['resource_type' => 'resolution', 'resource_id' => $resolution->id,
                    'actor_id' => $owner->id, 'revision' => 1, 'action' => 'resolution.accepted']);
                $this->assertSame(0, DB::table('content_revisions')->where('resource_type', 'proposal')->whereIn('resource_id', [$proposal->id, $otherProposal->id])->count());
            } else {
                $this->assertSame('proposed', $proposal->state->value);
                $this->assertSame('proposed', $otherProposal->state->value);
                if ($edited) {
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
            $proposalIds = Proposal::where('request_id', $parent->id)->pluck('id');
            $resolutionIds = DB::table('resolutions')->where('request_id', $parent->id)->pluck('id');
            $eventIds = $proposalIds->merge($resolutionIds)->all();
            DB::table('notification_outbox')->whereIn('event_id', $eventIds)->delete();
            DB::table('internal_notifications')->whereIn('event_id', $eventIds)->delete();
            DB::table('api_idempotency')->whereIn('user_id', [$owner->id, $contributor->id, $otherContributor->id])
                ->whereIn('key_hash', [(new IdempotencyKey(self::KEY))->hash, (new IdempotencyKey(self::OTHER_KEY))->hash])->delete();
            DB::table('content_revisions')->where('resource_type', 'resolution')->whereIn('resource_id', $resolutionIds)->delete();
            DB::table('content_revisions')->where('resource_type', 'proposal')->whereIn('resource_id', $proposalIds)->delete();
            DB::table('content_revisions')->where('resource_type', 'help_request')->where('resource_id', $parent->id)->delete();
            DB::table('resolutions')->where('request_id', $parent->id)->delete();
            ProposalRevision::whereIn('proposal_id', $proposalIds)->delete();
            Proposal::where('request_id', $parent->id)->delete();
            $parent->delete();
        }
    }

    /** @param array<string, string> $packet */
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
                    $this->fail('Le processus B19 a terminé avant l’attente du verrou : '.$process->getErrorOutput());
                }
                $process->checkTimeout();
            }
            DB::statement('SELECT pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b19_worker' AND wait_event_type = 'Lock'")->total;
            if ($waiting === $expected) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->assertSame($expected, $waiting, 'Les processus PHP doivent attendre de vrais verrous PostgreSQL.');
    }
}
