<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Models\Capsules\CapsuleVersion;
use App\Models\Technology;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;

final class CapsuleVersionTechnologiesSchemaTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_attachment_is_persisted_with_version_label(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();

        $version->technologies()->attach($technology, ['version_label' => '^1.2']);

        $this->assertDatabaseHas('capsule_version_technologies', [
            'version_id' => $version->id,
            'technology_id' => $technology->id,
            'version_label' => '^1.2',
        ]);
    }

    public function test_null_version_label_is_accepted(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();

        $version->technologies()->attach($technology);

        $this->assertDatabaseHas('capsule_version_technologies', [
            'version_id' => $version->id,
            'technology_id' => $technology->id,
            'version_label' => null,
        ]);
    }

    public function test_pair_is_unique(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();
        $version->technologies()->attach($technology);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_version_technologies_pkey/i');

        DB::table('capsule_version_technologies')->insert([
            'version_id' => $version->id,
            'technology_id' => $technology->id,
            'version_label' => '^2.0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_version_label_length_is_enforced(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('capsule_version_technologies')->insert([
            'version_id' => $version->id,
            'technology_id' => $technology->id,
            'version_label' => str_repeat('x', 41),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_unknown_technology_is_refused(): void
    {
        $version = CapsuleVersion::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/foreign key|technology_id/i');

        DB::table('capsule_version_technologies')->insert([
            'version_id' => $version->id,
            'technology_id' => '00000000-0000-4000-8000-000000000000',
            'version_label' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_version_deletion_cascades_to_technologies(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();
        $version->technologies()->attach($technology, ['version_label' => '^3.0']);

        $version->delete();

        $this->assertDatabaseMissing('capsule_version_technologies', [
            'version_id' => $version->id,
            'technology_id' => $technology->id,
        ]);
    }

    public function test_technology_deletion_is_restricted_while_attached(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();
        $version->technologies()->attach($technology);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/foreign key|technology_id/i');

        $technology->delete();
    }
}
