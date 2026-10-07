<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\ContributionRole;
use App\Models\Capsules\CapsuleContributor;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\PostgresTestCase;

final class CapsuleContributorsSchemaTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_contributor_is_persisted(): void
    {
        $contributor = CapsuleContributor::factory()->create();

        $this->assertDatabaseHas('capsule_contributors', [
            'id' => $contributor->id,
            'version_id' => $contributor->version_id,
            'user_id' => $contributor->user_id,
            'contribution_role' => 'diagnosis',
        ]);
    }

    public function test_triplet_is_unique(): void
    {
        $version = CapsuleVersion::factory()->create();
        $user = User::factory()->create();

        CapsuleContributor::factory()->create([
            'version_id' => $version->id,
            'user_id' => $user->id,
            'contribution_role' => ContributionRole::Diagnosis,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsule_contributors_triplet_unique/i');

        CapsuleContributor::factory()->create([
            'version_id' => $version->id,
            'user_id' => $user->id,
            'contribution_role' => ContributionRole::Diagnosis,
        ]);
    }

    public function test_same_user_can_hold_different_roles_on_the_same_version(): void
    {
        $version = CapsuleVersion::factory()->create();
        $user = User::factory()->create();

        $diagnosis = CapsuleContributor::factory()->create([
            'version_id' => $version->id,
            'user_id' => $user->id,
            'contribution_role' => ContributionRole::Diagnosis,
        ]);
        $fix = CapsuleContributor::factory()->create([
            'version_id' => $version->id,
            'user_id' => $user->id,
            'contribution_role' => ContributionRole::Fix,
        ]);

        $this->assertNotSame($diagnosis->id, $fix->id);
    }

    public function test_version_deletion_cascades_to_contributors(): void
    {
        $contributor = CapsuleContributor::factory()->create();
        $version = CapsuleVersion::findOrFail($contributor->version_id);

        $version->delete();

        $this->assertDatabaseMissing('capsule_contributors', ['id' => $contributor->id]);
    }

    public function test_user_deletion_is_restricted_while_contributions_exist(): void
    {
        $contributor = CapsuleContributor::factory()->create();
        $user = User::findOrFail($contributor->user_id);

        $this->expectException(QueryException::class);

        $user->delete();
    }
}
