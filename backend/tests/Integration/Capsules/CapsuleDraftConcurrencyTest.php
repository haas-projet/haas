<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Collaboration\ProposalState;
use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\Role;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
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

final class CapsuleDraftConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    /** @return iterable<string, array{string}> */
    public static function races(): iterable
    {
        foreach (['create-replay', 'slug-collision', 'edit-version', 'suspension', 'owner-change', 'source-reopened', 'source-hidden', 'source-author-suspended', 'source-author-unverified', 'source-author-cross-locks'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('races')]
    public function test_two_real_processes_recheck_the_locked_state(string $case): void
    {
        $actor = User::factory()->verified()->create(['role' => Role::Moderator]);
        $crossLocks = $case === 'source-author-cross-locks';
        $secondActor = $case === 'slug-collision' || $crossLocks ? User::factory()->verified()->create(['role' => Role::Moderator]) : $actor;
        $edit = in_array($case, ['edit-version', 'suspension', 'owner-change'], true);
        $capsule = $edit ? Capsule::factory()->create(['owner_id' => $actor->id]) : null;
        $version = $capsule === null ? null : CapsuleVersion::factory()->create(['capsule_id' => $capsule->id]);
        $source = null;
        $secondSource = null;
        $sourceAuthor = null;
        if (str_starts_with($case, 'source-')) {
            $sourceAuthor = $crossLocks ? $secondActor : (str_starts_with($case, 'source-author-') ? User::factory()->verified()->create() : $actor);
            $source = $this->resolvedSource($sourceAuthor, $actor);
            $secondSource = $crossLocks ? $this->resolvedSource($actor, $secondActor) : $source;
        }
        $database = config('database.connections.pgsql');
        $environment = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'], 'DB_DATABASE' => $database['database'], 'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password']];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/capsule-draft-concurrently.php')], base_path(), $environment, $stream, 45);
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
            if ($crossLocks) {
                HelpRequest::whereIn('id', [$source?->id, $secondSource?->id])->orderBy('id')->lockForUpdate()->get();
            } else {
                User::whereIn('id', [$actor->id, $secondActor->id])->orderBy('id')->lockForUpdate()->get();
            }
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode(['actor_id' => $index === 0 ? $actor->id : $secondActor->id, 'capsule_id' => $capsule?->id, 'version_id' => $version?->id,
                    'source_id' => $index === 0 ? $source?->id : $secondSource?->id, 'command' => $edit ? 'edit' : ($source === null ? 'create' : 'source'),
                    'key' => $case === 'create-replay' || $index === 0 ? 'b2300000-3333-4444-8888-123456789abc' : 'b2300000-3333-4444-8888-123456789abd'], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            $deadline = microtime(true) + 15;
            do {
                foreach ($processes as $process) {
                    $process->isRunning();
                    $process->checkTimeout();
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b23_worker' AND wait_event_type = 'Lock'")->total;
                if ($waiting === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Les deux processus doivent réellement attendre le verrou PostgreSQL.');
            match ($case) {
                'suspension' => $actor->forceFill(['status' => 'suspended'])->save(),
                'owner-change' => $capsule->forceFill(['owner_id' => User::factory()->verified()->create()->id])->save(),
                'source-reopened' => $source?->forceFill(['state' => HelpRequestState::Open])->save(),
                'source-hidden' => $source?->forceFill(['hidden_at' => now()->utc()])->save(),
                'source-author-suspended' => $sourceAuthor?->forceFill(['status' => 'suspended'])->save(),
                'source-author-unverified' => $sourceAuthor?->forceFill(['email_verified_at' => null])->save(),
                default => null,
            };
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }
            if ($crossLocks) {
                $this->assertContains('CONFLICT', $outcomes, 'Les verrous croisés doivent être refusés sans deadlock.');
                $this->assertSame([], array_values(array_diff($outcomes, ['APPLIED', 'CONFLICT'])));
            }
            $expected = match ($case) {
                'source-author-cross-locks' => $outcomes,
                'create-replay' => ['APPLIED', 'APPLIED'],
                'slug-collision', 'edit-version' => ['APPLIED', 'CONFLICT'],
                'source-reopened' => ['INVALID', 'INVALID'],
                default => ['FORBIDDEN', 'FORBIDDEN'],
            };
            $this->assertEqualsCanonicalizing($expected, $outcomes);
            $applied = in_array('APPLIED', $expected, true);
            $this->assertDatabaseCount('capsules', $edit || $applied ? 1 : 0);
            $this->assertDatabaseCount('capsule_versions', $edit || $applied ? 1 : 0);
            $this->assertDatabaseCount('api_idempotency', $applied && ! $edit ? 1 : 0);
            $this->assertDatabaseCount('content_revisions', $applied ? 1 : 0);
            if ($version !== null) {
                $this->assertSame($applied ? 2 : 1, $version->refresh()->lock_version);
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
        }
    }

    private function resolvedSource(User $author, User $proposer): HelpRequest
    {
        $source = HelpRequest::factory()->create(['author_id' => $author->id, 'state' => HelpRequestState::Resolved]);
        $proposal = Proposal::factory()->create(['request_id' => $source->id, 'author_id' => $proposer->id, 'state' => ProposalState::Accepted]);
        Resolution::factory()->create(['request_id' => $source->id, 'proposal_id' => $proposal->id, 'accepted_by' => $author->id]);

        return $source;
    }
}
