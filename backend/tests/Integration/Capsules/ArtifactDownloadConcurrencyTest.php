<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\StreamArtifactService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\Capsules\ArtifactKitFixture;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\TestDatabaseGuard;

final class ArtifactDownloadConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_direct_deactivation_waits_without_a_version_artifact_deadlock(): void
    {
        Storage::fake('local');
        [$actor, $capsule, $version, $artifact] = ArtifactKitFixture::create('course-directe');
        $database = config('database.connections.pgsql');
        $environment = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => $database['database'], 'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password']];
        $stream = new InputStream;
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/artifact-download-concurrently.php')], base_path(), $environment, $stream, 45);
        try {
            $process->start();
            while (! str_contains($process->getOutput(), 'READY') && $process->isRunning()) {
                $process->checkTimeout();
                usleep(10000);
            }
            $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
            DB::beginTransaction();
            Capsule::whereKey($capsule->id)->lockForUpdate()->firstOrFail();
            CapsuleVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            $stream->write(json_encode(['actor' => $actor->id, 'capsule' => $capsule->id, 'version' => $version->id,
                'artifact' => $artifact->id, 'command' => 'deactivate'], JSON_THROW_ON_ERROR)."\n");
            $stream->close();
            $deadline = microtime(true) + 15;
            do {
                $process->isRunning();
                $process->checkTimeout();
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b27_worker' AND wait_event_type = 'Lock'")->total;
                if ($waiting === 1) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(1, $waiting, $process->getOutput().$process->getErrorOutput());
            app(StreamArtifactService::class)->handle($actor, $capsule->id, $version->id, $artifact->id)->close();
            DB::commit();
            $this->assertSame(0, $process->wait(), $process->getOutput().$process->getErrorOutput());
            $this->assertStringContainsString('APPLIED', $process->getOutput());
            $this->assertSame('inactive', $artifact->refresh()->distribution_status->value);
            $this->expectException(ArtifactUnavailable::class);
            app(StreamArtifactService::class)->handle($actor, $capsule->id, $version->id, $artifact->id);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $stream->close();
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function races(): iterable
    {
        yield 'two issuances serialize audit' => ['audit', 'issue'];
        foreach (['suspended', 'unverified', 'hidden', 'withdrawn', 'inactive', 'corrupted'] as $state) {
            foreach (['issue', 'stream'] as $command) {
                yield $state.' '.$command => [$state, $command];
            }
        }
    }

    #[DataProvider('races')]
    public function test_two_waiting_processes_recheck_current_state(string $case, string $command): void
    {
        Storage::fake('local');
        [$actor, $capsule, $version, $artifact] = ArtifactKitFixture::create('course-kit');
        $second = $case === 'audit' ? User::factory()->verified()->create() : $actor;
        $database = config('database.connections.pgsql');
        $environment = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => $database['database'], 'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password']];
        $streams = [new InputStream, new InputStream];
        $processes = [];
        try {
            foreach ($streams as $stream) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/artifact-download-concurrently.php')], base_path(), $environment, $stream, 45);
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
            if (in_array($case, ['suspended', 'unverified'], true)) {
                User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            } else {
                Capsule::whereKey($capsule->id)->lockForUpdate()->firstOrFail();
                CapsuleVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
                Artifact::whereKey($artifact->id)->lockForUpdate()->firstOrFail();
            }
            foreach ($streams as $index => $stream) {
                $stream->write(json_encode(['actor' => $index === 0 ? $actor->id : $second->id, 'capsule' => $capsule->id,
                    'version' => $version->id, 'artifact' => $artifact->id, 'command' => $command], JSON_THROW_ON_ERROR)."\n");
                $stream->close();
            }
            $deadline = microtime(true) + 15;
            do {
                foreach ($processes as $process) {
                    $process->isRunning();
                    $process->checkTimeout();
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::selectOne("SELECT count(*)::int AS total FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_b27_worker' AND wait_event_type = 'Lock'")->total;
                if ($waiting === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Les deux vrais processus doivent attendre un verrou PostgreSQL.');
            match ($case) {
                'suspended' => $actor->forceFill(['status' => 'suspended'])->save(),
                'unverified' => $actor->forceFill(['email_verified_at' => null])->save(),
                'hidden' => $capsule->forceFill(['visibility' => 'hidden'])->save(),
                'withdrawn' => $version->forceFill(['state' => 'withdrawn'])->save(),
                'inactive' => $artifact->forceFill(['distribution_status' => 'inactive'])->save(),
                'corrupted' => Storage::disk('local')->put('artifacts/'.$artifact->private_path, 'fixture corrompue'),
                default => null,
            };
            DB::commit();
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $outcomes[] = end($lines);
            }
            $this->assertSame($case === 'audit' ? ['APPLIED', 'APPLIED'] : ['UNAVAILABLE', 'UNAVAILABLE'], $outcomes);
            $audit = DB::table('content_revisions')->where('resource_id', $version->id)->where('action', 'capsule.version.artifact.download_issued')->orderBy('revision')->get();
            $this->assertSame($case === 'audit' ? [1, 2] : [], $audit->pluck('revision')->all());
            foreach ($audit as $entry) {
                $metadata = json_decode($entry->metadata, true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame($artifact->sha256, $metadata['sha256']);
                $this->assertSame($artifact->notices_sha256, $metadata['notices_sha256']);
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
}
