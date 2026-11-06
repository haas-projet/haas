<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ContributionRole;
use App\Enums\Identity\Role;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\TestDatabaseGuard;

/**
 * B28 — Concurrence réelle (deux processus PHP) sur `POST .../reuse-reports`.
 * Même patron que `CapsuleWritesConcurrencyTest` (B24) :
 *  - le parent tient un verrou sur `users` dans sa transaction ;
 *  - les deux enfants bootstrap Laravel, s'annoncent `READY`, puis
 *    appellent `RecordReuseReportService::handle` ;
 *  - `IdempotencyService` verrouille l'acteur, les deux enfants attendent
 *    le même verrou (`pg_stat_activity.application_name = 'haas_b28_reuse_worker'`),
 *    le parent commit libère la course.
 */
final class ReuseReportConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        try {
            TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
            foreach (['internal_notifications', 'notification_outbox'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('kind', 'capsule.review.changes_requested')->delete();
                }
            }
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_concurrent_posts_with_same_key_write_a_single_report(): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key]);
        $member = User::factory()->verified()->create();
        [$capsule, $version] = $this->publishedVersion('rejeu-concurrent');
        $env = $this->childEnv($key);
        $payload = [
            'actor_id' => $member->id,
            'capsule_id' => $capsule->id,
            'version_id' => $version->id,
            'environment' => 'macOS 14 + PHP 8.4',
            'reported_at' => now()->format('Y-m-d'),
            'outcome' => 'reproduced',
            'observation' => 'Observation structurée longue de plus de vingt caractères.',
            'idempotency_key' => (string) Str::uuid(),
        ];
        // Même clé pour les deux processus : les deux doivent voir le même StoredCommandResult.
        $this->raceOnLock(
            $env,
            [$payload, $payload],
            fn () => User::whereKey($member->id)->lockForUpdate()->firstOrFail(),
            function (array $outcomes) use ($member, $version): void {
                $this->assertEqualsCanonicalizing(['UPDATED', 'UPDATED'], $outcomes);
                $this->assertSame(1, DB::table('reuse_reports')->where('user_id', $member->id)->where('version_id', $version->id)->count());
                $this->assertSame(0, DB::table('reuse_report_revisions')->count());
                $this->assertSame(1, DB::table('api_idempotency')->count());
            },
        );
    }

    public function test_two_concurrent_posts_with_distinct_keys_update_only_once_as_revision(): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key]);
        $member = User::factory()->verified()->create();
        [$capsule, $version] = $this->publishedVersion('revision-concurrente');
        $env = $this->childEnv($key);
        $base = [
            'actor_id' => $member->id,
            'capsule_id' => $capsule->id,
            'version_id' => $version->id,
            'environment' => 'macOS 14 + PHP 8.4',
            'reported_at' => now()->format('Y-m-d'),
            'outcome' => 'reproduced',
            'observation' => 'Observation structurée longue de plus de vingt caractères.',
        ];
        $payloads = [
            [...$base, 'idempotency_key' => (string) Str::uuid()],
            [...$base, 'idempotency_key' => (string) Str::uuid()],
        ];
        // Clés distinctes : l'IdempotencyService sérialise l'acteur ; une seule
        // ligne active est maintenue, le second POST archive la précédente.
        $this->raceOnLock(
            $env,
            $payloads,
            fn () => User::whereKey($member->id)->lockForUpdate()->firstOrFail(),
            function (array $outcomes) use ($member, $version): void {
                $this->assertEqualsCanonicalizing(['UPDATED', 'UPDATED'], $outcomes);
                $this->assertSame(1, DB::table('reuse_reports')->where('user_id', $member->id)->where('version_id', $version->id)->count());
                // Un seul enfant a enregistré une révision ; l'autre a créé la ligne initiale.
                $this->assertSame(1, DB::table('reuse_report_revisions')->count());
                $report = DB::table('reuse_reports')->where('user_id', $member->id)->sole();
                $this->assertSame(2, (int) $report->revision);
            },
        );
    }

    /** @return array{Capsule, CapsuleVersion} */
    private function publishedVersion(string $slug): array
    {
        $owner = User::factory()->verified()->create();
        $moderator = User::factory()->verified()->create(['role' => Role::Moderator]);
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => $slug]);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'body' => "## Procédure\n\nÉtape clairement décrite.",
            'limits' => 'Portée réduite.',
            'state' => CapsuleVersionState::Draft,
            'lock_version' => 1,
        ]);
        DB::table('capsule_contributors')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => $version->id,
            'user_id' => $owner->id,
            'contribution_role' => ContributionRole::Diagnosis->value,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        CapsuleVersion::whereKey($version->id)->update([
            'state' => CapsuleVersionState::Published->value,
            'reviewer_id' => $moderator->id,
            'published_at' => now()->utc(),
            'content_digest' => hash('sha256', $slug.' / 1.0.0'),
            'lock_version' => 2,
        ]);
        $version->refresh();

        return [$capsule, $version];
    }

    /** @return array<string, string> */
    private function childEnv(string $appKey): array
    {
        $db = config('database.connections.pgsql');

        return [
            'APP_KEY' => $appKey,
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'],
        ];
    }

    /**
     * @param  array<string, string>  $env
     * @param  list<array<string, mixed>>  $payloads
     * @param  \Closure(): mixed  $acquireParentLock
     * @param  \Closure(list<string>): void  $assertOutcomes
     */
    private function raceOnLock(array $env, array $payloads, \Closure $acquireParentLock, \Closure $assertOutcomes): void
    {
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/record-reuse-report-concurrently.php')], base_path(), $env, $stream, 30);
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
            $acquireParentLock();
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode($payloads[$index], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            $deadline = microtime(true) + 10;
            $waiting = 0;
            do {
                foreach ($processes as $process) {
                    $process->isRunning();
                    $process->checkTimeout();
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b28_reuse_worker' AND wait_event_type = 'Lock'")->total;
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
            $assertOutcomes($outcomes);
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
