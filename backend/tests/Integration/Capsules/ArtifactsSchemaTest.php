<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\ArtifactDistributionStatus;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\CapsuleVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class ArtifactsSchemaTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_artifact_is_persisted_with_default_inactive_status(): void
    {
        $artifact = Artifact::factory()->create();

        $this->assertDatabaseHas('artifacts', [
            'id' => $artifact->id,
            'version_id' => $artifact->version_id,
            'distribution_status' => 'inactive',
        ]);
        $this->assertSame(ArtifactDistributionStatus::Inactive, $artifact->refresh()->distribution_status);
    }

    public function test_sha256_format_is_enforced(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/artifacts_sha256_format/i');

        DB::table('artifacts')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => CapsuleVersion::factory()->create()->id,
            'private_path' => 'capsules/x/kit.zip',
            'sha256' => 'TOOSHORT',
            'size' => 1,
            'distribution_status' => 'inactive',
            'notices_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_size_must_be_positive(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/artifacts_size_positive/i');

        DB::table('artifacts')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => CapsuleVersion::factory()->create()->id,
            'private_path' => 'capsules/x/kit.zip',
            'sha256' => str_repeat('a', 64),
            'size' => 0,
            'distribution_status' => 'inactive',
            'notices_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_version_deletion_cascades_to_artifacts(): void
    {
        $artifact = Artifact::factory()->create();
        $version = CapsuleVersion::findOrFail($artifact->version_id);

        $version->delete();

        $this->assertDatabaseMissing('artifacts', ['id' => $artifact->id]);
    }

    public function test_approved_artifact_is_unique_per_version_and_sha256(): void
    {
        $version = CapsuleVersion::factory()->create();
        $digest = str_repeat('b', 64);

        Artifact::factory()->approved()->create(['version_id' => $version->id, 'sha256' => $digest]);
        // Un second artefact inactif avec le même digest reste autorisé.
        Artifact::factory()->create(['version_id' => $version->id, 'sha256' => $digest]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/artifacts_version_sha_approved_unique/i');

        Artifact::factory()->approved()->create(['version_id' => $version->id, 'sha256' => $digest]);
    }
}
