<?php

declare(strict_types=1);

namespace Tests\Integration\VerificationCases;

use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\Role;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Models\HelpRequest;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\Mdev44\DomainTables;

final class VerificationCaseReviewConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        try {
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_concurrent_reviews_elect_a_single_decision(): void
    {
        $author = User::factory()->verified()->create();
        $parentAuthor = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->create([
            'author_id' => $parentAuthor->id,
            'state' => HelpRequestState::Open,
        ]);
        $case = VerificationCase::factory()->create([
            'author_id' => $author->id,
            'help_request_id' => $parent->id,
            'state' => VerificationCaseState::Submitted,
            'current_revision' => 1,
        ]);
        VerificationCaseRevision::factory()->create([
            'case_id' => $case->id,
            'revision_no' => 1,
            'created_at' => now()->subMinute(),
            'submitted_at' => now(),
        ]);
        $reviewerA = User::factory()->verified()->create(['role' => Role::Moderator]);
        $reviewerB = User::factory()->verified()->create(['role' => Role::Admin]);

        $db = config('database.connections.pgsql');
        $env = [
            'APP_KEY' => (string) config('app.key'), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'],
        ];
        $inputs = [
            ['user_id' => $reviewerA->id, 'case_id' => $case->id, 'lock_version' => $case->lock_version,
                'decision' => 'reviewed', 'idempotency_key' => (string) Str::uuid()],
            ['user_id' => $reviewerB->id, 'case_id' => $case->id, 'lock_version' => $case->lock_version,
                'decision' => 'declined', 'reason' => 'Motif de refus argumenté par le relecteur B.',
                'idempotency_key' => (string) Str::uuid()],
        ];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/review-verification-case-concurrently.php')], base_path(), $env, $stream, 30);
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
            VerificationCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode($inputs[$index], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            $deadline = microtime(true) + 10;
            do {
                foreach ($processes as $process) {
                    $process->isRunning();
                    $process->checkTimeout();
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND wait_event_type IN ('Lock', 'LWLock') AND query ILIKE '%verification_cases%'")->total;
                if ($waiting >= 1) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertGreaterThanOrEqual(1, $waiting, 'Au moins un relecteur doit attendre son verrou.');
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $exitCode = $process->wait();
                $this->assertSame(0, $exitCode, 'stderr='.$process->getErrorOutput().' stdout='.$process->getOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }
            sort($outcomes);
            $this->assertSame(['ACCEPTED', 'STALE'], $outcomes);
            $final = VerificationCase::whereKey($case->id)->firstOrFail();
            $this->assertContains($final->state, [VerificationCaseState::Reviewed, VerificationCaseState::Declined]);
            // Une seule décision = une seule ligne d'audit.
            $audit = DB::table('content_revisions')
                ->where('resource_type', 'verification_case')
                ->where('resource_id', $case->id)
                ->whereIn('action', ['verification_case.reviewed', 'verification_case.declined'])
                ->count();
            $this->assertSame(1, $audit);
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
