<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class CapsuleVersionsSchemaTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_draft_version_is_persisted_with_default_state(): void
    {
        $capsule = Capsule::factory()->create();
        $version = CapsuleVersion::factory()->create(['capsule_id' => $capsule->id, 'version_label' => '1.0.0']);

        $this->assertDatabaseHas('capsule_versions', [
            'id' => $version->id,
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'state' => 'draft',
            'reviewer_id' => null,
            'published_at' => null,
        ]);
        $this->assertSame(CapsuleVersionState::Draft, $version->refresh()->state);
    }

    public function test_version_label_is_unique_per_capsule_but_shared_across_capsules(): void
    {
        $capsuleA = Capsule::factory()->create();
        $capsuleB = Capsule::factory()->create();

        CapsuleVersion::factory()->create(['capsule_id' => $capsuleA->id, 'version_label' => '1.0.0']);
        // Même label autorisé sur une autre capsule.
        CapsuleVersion::factory()->create(['capsule_id' => $capsuleB->id, 'version_label' => '1.0.0']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_capsule_label_unique/i');

        CapsuleVersion::factory()->create(['capsule_id' => $capsuleA->id, 'version_label' => '1.0.0']);
    }

    public function test_capsule_deletion_cascades_to_versions(): void
    {
        $capsule = Capsule::factory()->create();
        $version = CapsuleVersion::factory()->create(['capsule_id' => $capsule->id]);

        $capsule->delete();

        $this->assertDatabaseMissing('capsule_versions', ['id' => $version->id]);
    }

    public function test_reviewer_deletion_preserves_attribution_by_refusing_the_delete(): void
    {
        $reviewer = User::factory()->create();
        $version = CapsuleVersion::factory()->inReview($reviewer)->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_reviewer_id_foreign/i');
        $reviewer->delete();
    }

    public function test_published_at_requires_published_state(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_published_at_requires_state/i');

        DB::table('capsule_versions')->insert([
            'id' => (string) Str::uuid(),
            'capsule_id' => Capsule::factory()->create()->id,
            'version_label' => '2.0.0',
            'body' => 'contenu',
            'limits' => null,
            'state' => 'draft',
            'reviewer_id' => null,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_body_cannot_be_blank(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_body_nonempty/i');

        DB::table('capsule_versions')->insert([
            'id' => (string) Str::uuid(),
            'capsule_id' => Capsule::factory()->create()->id,
            'version_label' => '3.0.0',
            'body' => '   ',
            'limits' => null,
            'state' => 'draft',
            'reviewer_id' => null,
            'published_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_capsule_versions_relation_is_wired_both_ways(): void
    {
        $capsule = Capsule::factory()->create();
        CapsuleVersion::factory()->sequence(
            ['version_label' => '1.0.1'],
            ['version_label' => '1.0.2'],
            ['version_label' => '1.0.3'],
        )->count(3)->create(['capsule_id' => $capsule->id]);

        $this->assertSame(3, $capsule->versions()->count());
        $first = $capsule->versions()->firstOrFail();
        $relation = $first->capsule()->firstOrFail();
        $this->assertSame($capsule->id, $relation->getKey());
    }
}
