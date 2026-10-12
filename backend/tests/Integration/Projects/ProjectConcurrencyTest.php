<?php

namespace Tests\Integration\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\PostgresTestCase;
use Tests\Support\TestDatabaseGuard;

final class ProjectConcurrencyTest extends PostgresTestCase
{
    use DatabaseMigrations;

    private const string KEY = 'bc020000-3333-4444-8888-123456789abc';

    #[DataProvider('races')]
    public function test_real_workers_serialize_intentions_versions_and_current_rights(string $case): void
    {
        $owner = User::factory()->verified()->create();
        $project = $case === 'create-replay' ? null : Project::factory()->create(['owner_id' => $owner->id, 'name' => 'Projet avant les processus']);
        $base = ['actor_id' => $owner->id, 'project_id' => $project === null ? (string) Str::uuid() : $project->id,
            'operation' => $case === 'create-replay' ? 'create' : 'update', 'key' => self::KEY, 'name' => 'Projet processus A'];
        $other = array_replace($base, $case === 'update-distinct' ? ['key' => (string) Str::uuid(), 'name' => 'Projet processus B'] : []);
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
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/project-concurrently.php')], base_path(), $environment, $stream, 45);
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
            User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            foreach ([$base, $other] as $index => $packet) {
                $streams[$index]->write(json_encode($packet, JSON_THROW_ON_ERROR)."\n");
                $streams[$index]->close();
            }
            $deadline = microtime(true) + 15;
            do {
                foreach ($processes as $process) {
                    $process->checkTimeout();
                    if (! $process->isRunning()) {
                        $this->fail('Le processus doit attendre un verrou PostgreSQL réel.');
                    }
                }
                DB::statement('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::scalar("SELECT count(*)::int FROM pg_stat_activity WHERE datname = current_database() AND application_name = 'haas_bc02_worker' AND wait_event_type = 'Lock'");
                if ($waiting === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting);
            if ($case === 'owner-suspended') {
                $owner->forceFill(['status' => 'suspended'])->save();
            } elseif ($case === 'project-hidden') {
                $this->assertNotNull($project);
                $project->forceFill(['hidden_at' => now()])->save();
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
                if ($lines === []) {
                    $this->fail('Le processus BC02 doit produire un résultat JSON.');
                }
                $result = json_decode($lines[array_key_last($lines)], true, flags: JSON_THROW_ON_ERROR);
                $this->assertIsArray($result);
                $results[] = $result;
            }
            $statuses = array_column($results, 'status');
            sort($statuses);
            $forbidden = in_array($case, ['owner-suspended', 'project-hidden'], true);
            $this->assertSame($forbidden ? ['FORBIDDEN', 'FORBIDDEN'] : ($case === 'update-distinct' ? ['APPLIED', 'CONFLICT'] : ['APPLIED', 'APPLIED']), $statuses);
            $this->assertSame($forbidden ? 0 : 1, DB::table('content_revisions')->where('resource_type', 'project')->where('actor_id', $owner->id)->count());
            $this->assertSame($forbidden ? 0 : 1, DB::table('api_idempotency')->where('user_id', $owner->id)->count());
            if (! $forbidden) {
                $current = Project::where('owner_id', $owner->id)->sole();
                $this->assertSame($case === 'create-replay' ? 1 : 2, $current->lock_version);
                $this->assertSame('draft', $current->publication_state->value);
                $this->assertNull($current->published_at);
                foreach ($results as $result) {
                    if ($result['status'] === 'APPLIED') {
                        $this->assertSame($current->id, $result['project_id']);
                    }
                }
            } else {
                $this->assertNotNull($project);
                $this->assertSame(1, $project->refresh()->lock_version);
                $this->assertSame('Projet avant les processus', $project->name);
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
            $ids = Project::where('owner_id', $owner->id)->pluck('id');
            DB::table('api_idempotency')->where('user_id', $owner->id)->delete();
            DB::table('content_revisions')->where('resource_type', 'project')->whereIn('resource_id', $ids)->delete();
            DB::table('project_technologies')->whereIn('project_id', $ids)->delete();
            Project::whereIn('id', $ids)->delete();
        }
    }

    /** @return iterable<string, array{string}> */
    public static function races(): iterable
    {
        foreach (['create-replay', 'update-replay', 'update-distinct', 'owner-suspended', 'project-hidden'] as $case) {
            yield $case => [$case];
        }
    }
}
