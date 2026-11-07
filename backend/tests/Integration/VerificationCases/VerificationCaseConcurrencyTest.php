<?php

declare(strict_types=1);

namespace Tests\Integration\VerificationCases;

use App\Enums\HelpRequests\HelpRequestState;
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

final class VerificationCaseConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        try {
            // Les processus concurrents écrivent verification_cases /
            // verification_case_revisions hors de la transaction du test.
            // Même nettoyage que ConcurrentLabRunTest (B34, commit 5458594) :
            // on dépose les tables du domaine via DomainTables::dropAll()
            // avant que DatabaseMigrations ne joue le rollback.
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_concurrent_patches_with_the_same_lock_version_elect_a_single_winner(): void
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
        ]);
        VerificationCaseRevision::factory()->create([
            'case_id' => $case->id,
            'revision_no' => 1,
            'digest' => str_repeat('0', 64),
        ]);

        $db = config('database.connections.pgsql');
        $env = [
            'APP_KEY' => (string) config('app.key'), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'],
        ];
        $bodyA = $this->body('Vérifier la nouvelle règle depuis le processus A soumis');
        $bodyB = $this->body('Vérifier la nouvelle règle depuis le processus B soumis');
        $inputs = [
            ['user_id' => $author->id, 'case_id' => $case->id, 'lock_version' => $case->lock_version,
                'body' => $bodyA, 'idempotency_key' => (string) Str::uuid()],
            ['user_id' => $author->id, 'case_id' => $case->id, 'lock_version' => $case->lock_version,
                'body' => $bodyB, 'idempotency_key' => (string) Str::uuid()],
        ];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/revise-verification-case-concurrently.php')], base_path(), $env, $stream, 30);
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
            // Barrière : verrou sur la ligne cas qui force les deux processus
            // à attendre après leur SELECT ... FOR UPDATE dans la transaction.
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
            $this->assertGreaterThanOrEqual(1, $waiting, 'Au moins un processus doit être en attente du verrou.');
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
            // Un seul gagnant : lock_version avance d'une seule unité.
            $final = VerificationCase::whereKey($case->id)->firstOrFail();
            $this->assertSame($case->lock_version + 1, (int) $final->lock_version);
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

    /** @return array<string, mixed> */
    private function body(string $title): array
    {
        return [
            'title' => $title,
            'context' => 'Vérifier le comportement attendu après rejeu identique du même évènement.',
            'steps' => '1. Appeler avec une clé.'."\n".'2. Rejouer avec la même clé.'."\n".'3. Observer.',
            'expected' => 'Le compteur ne change pas au rejeu.',
            'observed' => null,
            'environment' => 'Documentaire.',
        ];
    }
}
