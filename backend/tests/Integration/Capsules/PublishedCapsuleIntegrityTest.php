<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleContributor;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\PostgresTestCase;

final class PublishedCapsuleIntegrityTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_withdrawal_keeps_the_publication_date_and_documented_content(): void
    {
        $version = CapsuleVersion::factory()->published(User::factory()->create())->create();
        $before = $version->only(['body', 'limits', 'version_label', 'reviewer_id', 'published_at']);

        $version->forceFill(['state' => CapsuleVersionState::Withdrawn, 'lock_version' => 2])->save();

        $this->assertSame(CapsuleVersionState::Withdrawn, $version->refresh()->state);
        $this->assertEquals($before, $version->only(array_keys($before)));
        $this->assertSame(2, $version->lock_version);
    }

    #[DataProvider('immutableVersionChanges')]
    public function test_published_content_cannot_be_changed_through_direct_sql(string $column, string $value): void
    {
        $version = CapsuleVersion::factory()->published(User::factory()->create())->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_published_immutable/i');

        DB::table('capsule_versions')->where('id', $version->id)->update([$column => $value]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function immutableVersionChanges(): iterable
    {
        yield 'body' => ['body', 'Contenu remplacé'];
        yield 'limits' => ['limits', 'Limites remplacées'];
        yield 'version label' => ['version_label', '2.0.0'];
        yield 'publication date' => ['published_at', '2026-01-01 00:00:00+00'];
        yield 'return to draft' => ['state', 'draft'];
    }

    public function test_published_version_cannot_be_deleted_through_a_parent_cascade(): void
    {
        $version = CapsuleVersion::factory()->published(User::factory()->create())->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_published_immutable/i');

        DB::table('capsules')->where('id', $version->capsule_id)->delete();
    }

    public function test_owner_cannot_be_the_reviewer_of_a_published_version(): void
    {
        $owner = User::factory()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id]);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_independent_reviewer/i');

        CapsuleVersion::factory()->published($owner)->create(['capsule_id' => $capsule->id]);
    }

    public function test_contributor_cannot_review_their_own_published_contribution(): void
    {
        $contributor = User::factory()->create();
        $version = CapsuleVersion::factory()->create();
        CapsuleContributor::factory()->create(['version_id' => $version->id, 'user_id' => $contributor->id]);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_independent_reviewer/i');

        $version->forceFill(['state' => CapsuleVersionState::Published, 'reviewer_id' => $contributor->id, 'published_at' => now()])->save();
    }

    public function test_reviewer_deletion_cannot_erase_the_attribution(): void
    {
        $reviewer = User::factory()->create();
        CapsuleVersion::factory()->published($reviewer)->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_reviewer_id_foreign/i');

        $reviewer->delete();
    }

    public function test_published_provenance_cannot_be_rewritten(): void
    {
        $version = CapsuleVersion::factory()->published(User::factory()->create())->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsules_published_provenance_immutable/i');

        DB::table('capsules')->where('id', $version->capsule_id)->update(['editorial_origin' => 'Autre origine']);
    }

    public function test_published_technology_declarations_cannot_be_rewritten(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();
        $version->technologies()->attach($technology, ['version_label' => '13.x']);
        $version->forceFill(['state' => CapsuleVersionState::Published, 'reviewer_id' => User::factory()->create()->id, 'published_at' => now()])->save();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_published_attachments_immutable/i');

        $version->technologies()->updateExistingPivot($technology->id, ['version_label' => '14.x']);
    }

    public function test_published_contributor_attribution_cannot_be_deleted(): void
    {
        $version = CapsuleVersion::factory()->create();
        $contribution = CapsuleContributor::factory()->create(['version_id' => $version->id]);
        $version->forceFill(['state' => CapsuleVersionState::Published, 'reviewer_id' => User::factory()->create()->id, 'published_at' => now()])->save();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_published_attachments_immutable/i');

        $contribution->delete();
    }

    public function test_draft_content_and_technology_declarations_remain_editable(): void
    {
        $version = CapsuleVersion::factory()->create();
        $technology = Technology::factory()->create();
        $version->fill(['body' => 'Procédure amendée.'])->save();
        $version->technologies()->attach($technology, ['version_label' => '13.x']);
        $version->technologies()->updateExistingPivot($technology->id, ['version_label' => '13.1']);

        $this->assertSame('Procédure amendée.', $version->refresh()->body);
        $this->assertSame(1, $version->lock_version);
        $this->assertDatabaseHas('capsule_version_technologies', ['version_id' => $version->id, 'technology_id' => $technology->id, 'version_label' => '13.1']);
    }

    public function test_lock_version_cannot_be_nonpositive(): void
    {
        $version = CapsuleVersion::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_versions_lock_positive/i');

        DB::table('capsule_versions')->where('id', $version->id)->update(['lock_version' => 0]);
    }

    public function test_additive_upgrade_preserves_preexisting_published_content(): void
    {
        $version = CapsuleVersion::factory()->published(User::factory()->create())->create();
        $migration = $this->integrityMigration();
        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $before = $version->refresh()->getAttributes();

        (new ReflectionMethod($migration, 'up'))->invoke($migration);

        $this->assertEquals($before, $version->refresh()->getAttributes());
        $this->assertSame(1, $version->lock_version);
        $this->assertSame(CapsuleVersionState::Published, $version->state);
    }

    public function test_rollback_refuses_withdrawn_history_and_leaves_it_intact(): void
    {
        $version = CapsuleVersion::factory()->published(User::factory()->create())->create();
        $version->forceFill(['state' => CapsuleVersionState::Withdrawn])->save();
        $before = $version->refresh()->getAttributes();
        $migration = $this->integrityMigration();
        try {
            (new ReflectionMethod($migration, 'down'))->invoke($migration);
            $this->fail('Le retour au CHECK historique incompatible doit être refusé.');
        } catch (RuntimeException $exception) {
            $this->assertStringStartsWith('Rollback B22 refusé', $exception->getMessage());
        }
        $this->assertEquals($before, $version->refresh()->getAttributes());
    }

    private function integrityMigration(): Migration
    {
        $migration = require database_path('migrations/2026_10_07_200000_b22_preserve_published_capsule_versions.php');
        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }
}
