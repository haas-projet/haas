<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Identity\Role;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;

final class CapsuleReviewConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    /** @return iterable<string, array{string}> */
    public static function races(): iterable
    {
        foreach (['submit-replay', 'submit-version', 'review-replay', 'review-version', 'role-revoked', 'admin-contributor'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('races')]
    public function test_two_real_processes_recheck_the_locked_state(string $case): void
    {
        $review = ! str_starts_with($case, 'submit');
        $owner = User::factory()->verified()->create();
        $actor = $review ? User::factory()->verified()->create(['role' => $case === 'admin-contributor' ? Role::Admin : Role::Moderator]) : $owner;
        $secondActor = $case === 'review-version' ? User::factory()->verified()->create(['role' => Role::Moderator]) : $actor;
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id]);
        $version = CapsuleVersion::factory()->create(['capsule_id' => $capsule->id, 'state' => $review ? CapsuleVersionState::InReview : CapsuleVersionState::Draft, 'limits' => 'Limites explicites du scénario de concurrence.']);
        $database = config('database.connections.pgsql');
        $environment = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'], 'DB_DATABASE' => $database['database'], 'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password']];
        $processes = [];
        $streams = [new InputStream, new InputStream];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/capsule-review-concurrently.php')], base_path(), $environment, $stream, 45);
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
            User::whereIn('id', [$actor->id, $secondActor->id])->orderBy('id')->lockForUpdate()->get();
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode(['actor_id' => $index === 0 ? $actor->id : $secondActor->id, 'capsule_id' => $capsule->id, 'version_id' => $version->id,
                    'command' => $review ? 'review' : 'submit',
                    'key' => str_ends_with($case, 'replay') || $index === 0 ? 'b2400000-3333-4444-8888-123456789abc' : 'b2400000-3333-4444-8888-123456789abd'], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            $deadline = microtime(true) + 15;
            do {
                foreach ($processes as $process) {
                    $process->isRunning();
                    $process->checkTimeout();
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b24_worker' AND wait_event_type = 'Lock'")->total;
                if ($waiting === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Les deux processus doivent réellement attendre le verrou PostgreSQL.');
            if ($case === 'role-revoked') {
                $actor->forceFill(['role' => Role::Member])->save();
            } elseif ($case === 'admin-contributor') {
                DB::table('capsule_contributors')->insert(['id' => (string) Str::uuid(), 'version_id' => $version->id, 'user_id' => $actor->id, 'contribution_role' => 'fix', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }
            $expected = match ($case) {
                'submit-replay', 'review-replay' => ['APPLIED', 'APPLIED'],
                'submit-version', 'review-version' => ['APPLIED', 'CONFLICT'],
                default => ['FORBIDDEN', 'FORBIDDEN'],
            };
            $this->assertEqualsCanonicalizing($expected, $outcomes);
            $applied = in_array('APPLIED', $expected, true);
            $this->assertDatabaseCount('capsule_versions', 1);
            $this->assertDatabaseCount('capsule_version_reviews', $applied && $review ? 1 : 0);
            $this->assertDatabaseCount('api_idempotency', $applied ? 1 : 0);
            $this->assertDatabaseCount('content_revisions', $applied ? 1 : 0);
            $this->assertSame($applied ? 2 : 1, $version->refresh()->lock_version);
            $this->assertNull($version->published_at);
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
