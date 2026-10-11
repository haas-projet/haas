<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\CapsuleVersion;
use App\Services\Capsules\IssueArtifactDownloadService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\PostgresTestCase;
use Tests\Support\Capsules\ArtifactKitFixture;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\TestDatabaseGuard;

final class ArtifactPublicationSchemaTest extends PostgresTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
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

    /** @return iterable<string, array{string, mixed}> */
    public static function frozenFields(): iterable
    {
        yield 'path' => ['private_path', 'kits/other.zip'];
        yield 'digest' => ['sha256', str_repeat('b', 64)];
        yield 'size' => ['size', 1024];
        yield 'notice path' => ['notices_path', 'notices/other.txt'];
        yield 'notice digest' => ['notices_sha256', str_repeat('a', 64)];
        yield 'notice size' => ['notices_size', 1024];
        yield 'created at' => ['created_at', '2000-01-01 00:00:00'];
        yield 'id' => ['id', 'b2700000-1111-4111-8111-123456789abc'];
    }

    #[DataProvider('frozenFields')]
    public function test_published_kit_and_notice_fields_are_immutable(string $field, mixed $value): void
    {
        [, , , $artifact] = ArtifactKitFixture::create('kit-fige');
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/artifacts_published_immutable/');
        Artifact::whereKey($artifact->id)->update([$field => $value]);
    }

    public function test_kit_cannot_move_to_another_version(): void
    {
        [, , , $artifact] = ArtifactKitFixture::create('kit-deplace');
        $target = CapsuleVersion::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/artifacts_published_immutable/');
        Artifact::whereKey($artifact->id)->update(['version_id' => $target->id]);
    }

    public function test_kit_cannot_be_added_after_publication(): void
    {
        [, , $version] = ArtifactKitFixture::create('kit-ajout');
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/artifacts_published_immutable/');
        Artifact::factory()->create(['version_id' => $version->id]);
    }

    public function test_published_kit_cannot_be_deleted(): void
    {
        [, , , $artifact] = ArtifactKitFixture::create('kit-suppression');
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/artifacts_published_immutable/');
        $artifact->delete();
    }

    public function test_withdrawn_kit_metadata_remains_immutable(): void
    {
        [, , $version, $artifact] = ArtifactKitFixture::create('kit-retire-fige');
        $version->forceFill(['state' => 'withdrawn'])->save();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/artifacts_published_immutable/');
        Artifact::whereKey($artifact->id)->update(['notices_sha256' => str_repeat('c', 64)]);
    }

    public function test_distribution_can_be_deactivated_without_changing_versioned_metadata(): void
    {
        [$actor, $capsule, $version, $artifact] = ArtifactKitFixture::create('distribution-revoquee');
        $attributes = $artifact->getAttributes();
        $artifact->forceFill(['distribution_status' => 'inactive'])->save();
        foreach (['private_path', 'sha256', 'size', 'notices_path', 'notices_sha256', 'notices_size'] as $field) {
            $this->assertSame($attributes[$field], $artifact->refresh()->getAttribute($field));
        }
        $this->expectException(ArtifactUnavailable::class);
        app(IssueArtifactDownloadService::class)->handle($actor, $capsule->id, $version->id, $artifact->id);
    }

    public function test_legacy_notices_without_integrity_metadata_are_unavailable(): void
    {
        [$actor, $capsule, $version, $artifact] = ArtifactKitFixture::create('notice-legacy', ['notices_sha256' => null, 'notices_size' => null]);
        $this->assertNull($artifact->notices_sha256);
        $this->assertNull($artifact->notices_size);
        $this->expectException(ArtifactUnavailable::class);
        app(IssueArtifactDownloadService::class)->handle($actor, $capsule->id, $version->id, $artifact->id);
    }

    public function test_rollback_refuses_loss_of_versioned_notice_digests(): void
    {
        [, , , $artifact] = ArtifactKitFixture::create('rollback-kit');
        $migration = require database_path('migrations/2026_10_11_100000_b27_preserve_versioned_artifacts.php');
        try {
            $migration->down();
            $this->fail('Le rollback devait etre refuse.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Rollback B27 refuse', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('artifacts', 'notices_sha256'));
        $this->assertNotNull($artifact->refresh()->notices_sha256);
    }

    public function test_empty_additive_migration_can_roll_back_and_run_again(): void
    {
        $migration = require database_path('migrations/2026_10_11_100000_b27_preserve_versioned_artifacts.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('artifacts', 'notices_sha256'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('artifacts', 'notices_sha256'));
    }

    public function test_notice_integrity_pair_is_enforced(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/artifacts_notices_integrity/');
        Artifact::factory()->create(['id' => (string) Str::uuid(), 'notices_path' => 'notices/fixture.txt', 'notices_sha256' => str_repeat('a', 64)]);
    }
}
