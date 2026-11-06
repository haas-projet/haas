<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Models\Capsules\Artifact;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\PostgresTestCase;
use Tests\Support\Capsules\ArtifactKitFixture;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\TestDatabaseGuard;

final class ArtifactFreshMigrationTest extends PostgresTestCase
{
    protected function tearDown(): void
    {
        try {
            // Destruction des seules fixtures sur la base dédiée, pas un downgrade de production.
            TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
            DomainTables::dropAll();
            $this->assertSame(0, Artisan::call('migrate:rollback'));
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_fresh_migrations_keep_the_published_artifact_guard_active(): void
    {
        Storage::fake('local');

        for ($cycle = 1; $cycle <= 2; $cycle++) {
            // migrate:fresh efface les tables/triggers, mais conserve les fonctions PostgreSQL.
            $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
            $this->assertTrue(Schema::hasColumn('artifacts', 'notices_sha256'));
            [, , , $artifact] = ArtifactKitFixture::create('migration-repetee-'.$cycle);
            $digest = $artifact->sha256;

            try {
                Artifact::whereKey($artifact->id)->update(['sha256' => str_repeat('f', 64)]);
                $this->fail('La garde doit rester active après chaque recréation du schéma.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0] ?? null);
                $this->assertStringContainsString('artifacts_published_immutable', $exception->getMessage());
            }

            $this->assertSame($digest, $artifact->refresh()->sha256);
        }
    }
}
