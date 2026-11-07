<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Models\Capsules\CapsuleVersion;
use App\Models\Technology;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class B22TechnologySchemaTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_version_technologies_keep_the_declared_compatibility_and_relations(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();
        $version->technologies()->attach($technology, ['version_label' => '13.x']);

        $this->assertSame($technology->id, $version->technologies()->firstOrFail()->id);
        $this->assertDatabaseHas('capsule_version_technologies', ['version_id' => $version->id, 'technology_id' => $technology->id, 'version_label' => '13.x']);
    }

    public function test_technology_cannot_be_duplicated_in_a_version(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();
        $version->technologies()->attach($technology);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_version_technologies_pkey/i');

        $version->technologies()->attach($technology);
    }

    public function test_nonexistent_technology_is_rejected_by_the_foreign_key(): void
    {
        $version = CapsuleVersion::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_version_technologies_technology_id_foreign/i');

        $version->technologies()->attach((string) Str::uuid());
    }

    public function test_blank_declared_compatibility_is_rejected(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_version_technologies_label_format/i');

        $version->technologies()->attach($technology, ['version_label' => ' ']);
    }

    public function test_draft_deletion_removes_its_declarations_and_preserves_the_reference(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();
        $version->technologies()->attach($technology);
        $version->delete();

        $this->assertDatabaseMissing('capsule_version_technologies', ['version_id' => $version->id]);
        $this->assertModelExists($technology);
    }
}
