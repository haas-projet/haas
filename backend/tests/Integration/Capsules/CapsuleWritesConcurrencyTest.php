<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ReviewDecision;
use App\Enums\Identity\Role;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\CapsuleReviewNotificationFixtures;

/**
 * Concurrence réelle (deux processus PHP) sur les écritures de capsules
 * livrées par B23 et B24. Le patron suit `IdempotencyConcurrencyTest` et
 * `ModerationConcurrencyTest` : le parent tient une ligne verrouillée dans
 * sa propre transaction, les deux processus enfants attendent le même
 * verrou (pg_stat_activity), puis le parent commit libère la course.
 *
 * Depuis le refactor de B24, Submit/RequestChanges passent par
 * `IdempotencyService` qui verrouille l'acteur ; le parent lock donc la
 * ligne `users` plutôt que `capsule_versions` pour ces deux scénarios.
 */
final class CapsuleWritesConcurrencyTest extends PostgresTestCase
{
    use CapsuleReviewNotificationFixtures, DatabaseMigrations;

    public function test_two_posts_with_same_idempotency_key_write_only_one_capsule(): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key]);
        $actor = User::factory()->verified()->create(['role' => Role::Moderator]);
        $idempotencyKey = (string) Str::uuid();
        $env = $this->childEnv($key);
        $payload = [
            'mode' => 'create_draft',
            'actor_id' => $actor->id,
            'slug' => 'brique-concurrente',
            'idempotency_key' => $idempotencyKey,
        ];
        $this->raceOnLock(
            $env,
            $payload,
            fn () => User::whereKey($actor->id)->lockForUpdate()->firstOrFail(),
            function (array $outcomes): void {
                $this->assertEqualsCanonicalizing(['UPDATED', 'UPDATED'], $outcomes);
                $this->assertDatabaseCount('capsules', 1);
                $this->assertDatabaseCount('capsule_versions', 1);
                $this->assertDatabaseCount('api_idempotency', 1);
                $this->assertDatabaseCount('content_revisions', 1);
            },
        );
    }

    public function test_two_patches_with_same_lock_version_let_only_one_through(): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key]);
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => 'brouillon-concurrent']);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'body' => "## Procédure\nOriginal.",
            'state' => CapsuleVersionState::Draft,
            'lock_version' => 1,
        ]);
        $env = $this->childEnv($key);
        $payloads = [
            [
                'mode' => 'update_draft', 'actor_id' => $owner->id, 'capsule_id' => $capsule->id,
                'version_id' => $version->id, 'lock_version' => 1, 'nonce' => 'a',
            ],
            [
                'mode' => 'update_draft', 'actor_id' => $owner->id, 'capsule_id' => $capsule->id,
                'version_id' => $version->id, 'lock_version' => 1, 'nonce' => 'b',
            ],
        ];
        $this->raceOnLock(
            $env,
            $payloads,
            fn () => CapsuleVersion::whereKey($version->id)->lockForUpdate()->firstOrFail(),
            function (array $outcomes) use ($version): void {
                $this->assertEqualsCanonicalizing(['UPDATED', 'CONFLICT'], $outcomes);
                $version->refresh();
                $this->assertSame(2, $version->lock_version);
                $this->assertSame(CapsuleVersionState::Draft, $version->state);
            },
        );
    }

    public function test_two_concurrent_submit_reviews_transition_only_once(): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key]);
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => 'soumission-concurrente']);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'body' => "## Procédure\n\nÉtape clairement décrite pour la revue.",
            'limits' => 'Portée réduite au scénario cité.',
            'state' => CapsuleVersionState::Draft,
            'lock_version' => 1,
        ]);
        $env = $this->childEnv($key);
        // Clés d'idempotence différentes : les deux enfants exécutent réellement
        // l'opération (pas de rejeu), la course porte sur la transition d'état.
        $payloads = [
            [
                'mode' => 'submit_review', 'actor_id' => $owner->id, 'capsule_id' => $capsule->id,
                'version_id' => $version->id, 'lock_version' => 1,
                'idempotency_key' => (string) Str::uuid(),
            ],
            [
                'mode' => 'submit_review', 'actor_id' => $owner->id, 'capsule_id' => $capsule->id,
                'version_id' => $version->id, 'lock_version' => 1,
                'idempotency_key' => (string) Str::uuid(),
            ],
        ];
        $this->raceOnLock(
            $env,
            $payloads,
            fn () => User::whereKey($owner->id)->lockForUpdate()->firstOrFail(),
            function (array $outcomes) use ($version): void {
                $this->assertEqualsCanonicalizing(['UPDATED', 'CONFLICT'], $outcomes);
                $version->refresh();
                $this->assertSame(CapsuleVersionState::InReview, $version->state);
                $this->assertSame(2, $version->lock_version);
                $this->assertDatabaseCount('capsule_version_reviews', 0);
            },
        );
    }

    public function test_two_concurrent_request_changes_write_a_single_decision(): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key]);
        $owner = User::factory()->verified()->create();
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => 'revue-concurrente']);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'body' => "## Procédure\n\nÉtape clairement décrite pour la revue.",
            'limits' => 'Portée réduite au scénario cité.',
            'state' => CapsuleVersionState::InReview,
            'lock_version' => 2,
        ]);
        $env = $this->childEnv($key);
        // Même reviewer (verrou acteur partagé), clés différentes :
        // les deux tentatives exécutent, la course porte sur la décision.
        $note = 'Note de revue détaillée pour tester la concurrence des décisions.';
        $payloads = [
            [
                'mode' => 'request_changes', 'actor_id' => $reviewer->id, 'capsule_id' => $capsule->id,
                'version_id' => $version->id, 'lock_version' => 2, 'note' => $note,
                'idempotency_key' => (string) Str::uuid(),
            ],
            [
                'mode' => 'request_changes', 'actor_id' => $reviewer->id, 'capsule_id' => $capsule->id,
                'version_id' => $version->id, 'lock_version' => 2, 'note' => $note,
                'idempotency_key' => (string) Str::uuid(),
            ],
        ];
        $this->raceOnLock(
            $env,
            $payloads,
            fn () => User::whereKey($reviewer->id)->lockForUpdate()->firstOrFail(),
            function (array $outcomes) use ($version): void {
                $this->assertEqualsCanonicalizing(['UPDATED', 'CONFLICT'], $outcomes);
                $version->refresh();
                $this->assertSame(CapsuleVersionState::ChangesRequested, $version->state);
                $this->assertDatabaseCount('capsule_version_reviews', 1);
                $decision = DB::table('capsule_version_reviews')->sole();
                $this->assertSame(ReviewDecision::RequestChanges->value, $decision->decision);
            },
        );
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
     * @param  array<string, mixed>|list<array<string, mixed>>  $payload  — un seul payload partagé ou un payload par processus.
     * @param  \Closure(): mixed  $acquireParentLock
     * @param  \Closure(list<string>): void  $assertOutcomes
     */
    private function raceOnLock(array $env, array $payload, \Closure $acquireParentLock, \Closure $assertOutcomes): void
    {
        $payloads = array_is_list($payload) ? $payload : [$payload, $payload];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/write-capsule-concurrently.php')], base_path(), $env, $stream, 30);
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
                    // Process doit pomper ses pipes pour transmettre les entrées de la barrière.
                    $process->isRunning();
                    $process->checkTimeout();
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b24_capsule_worker' AND wait_event_type = 'Lock'")->total;
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
