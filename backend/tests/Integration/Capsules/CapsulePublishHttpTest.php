<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\ArtifactDistributionStatus;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ContributionRole;
use App\Enums\Capsules\ReviewDecision;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\PostgresTestCase;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\SpaHttpRequests;
use Tests\Support\TestDatabaseGuard;

/**
 * Contrats HTTP de B25 — publication d'une version en revue.
 * Reprend le patron SPA de `CapsuleReviewHttpTest` (session Sanctum,
 * `Idempotency-Key` par requête). Les scénarios de concurrence
 * (deux processus) sont dans `CapsuleWritesConcurrencyTest`.
 */
final class CapsulePublishHttpTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    protected function tearDown(): void
    {
        try {
            TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
            // Les versions retirées (B22), les décisions `approved` (B25) et les
            // intentions `capsule.review.changes_requested` (B24) bloqueraient toutes
            // le downgrade de leur migration respective. On drope le domaine capsules
            // en entier (enfants avant parents) : `migrate:rollback` revient à vide
            // sans rencontrer les gardes.
            foreach (['internal_notifications', 'notification_outbox'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('kind', 'capsule.review.changes_requested')->delete();
                }
            }
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_independent_moderator_publishes_an_in_review_version(): void
    {
        [, $capsule, $version] = $this->seedInReview();
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->loginAs($reviewer);
        $response = $this->publishRequest($capsule, $version);
        $response->assertOk();
        $this->assertSame('published', $response->json('data.state'));
        $version->refresh();
        $this->assertSame(CapsuleVersionState::Published, $version->state);
        $this->assertSame($reviewer->id, $version->reviewer_id);
        $this->assertNotNull($version->published_at);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $response->json('data.content_digest'));
        $this->assertSame($version->content_digest, $response->json('data.content_digest'));
        $this->assertDatabaseCount('capsule_version_reviews', 1);
        $this->assertSame(ReviewDecision::Approved->value, DB::table('capsule_version_reviews')->sole()->decision);
    }

    public function test_admin_who_owns_the_capsule_is_refused(): void
    {
        $admin = User::factory()->verified()->create(['role' => Role::Admin]);
        $capsule = Capsule::factory()->create(['owner_id' => $admin->id, 'slug' => 'auto-publier']);
        $version = $this->seedVersion($capsule, state: CapsuleVersionState::InReview, lockVersion: 2);
        $this->loginAs($admin);
        $this->publishRequest($capsule, $version)->assertStatus(403);
        $version->refresh();
        $this->assertSame(CapsuleVersionState::InReview, $version->state);
    }

    public function test_admin_who_is_contributor_is_refused(): void
    {
        [$owner, $capsule, $version] = $this->seedInReview();
        $contributorAdmin = User::factory()->verified()->create(['role' => Role::Admin]);
        DB::table('capsule_contributors')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => $version->id,
            'user_id' => $contributorAdmin->id,
            'contribution_role' => ContributionRole::Fix->value,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        $this->loginAs($contributorAdmin);
        $this->publishRequest($capsule, $version)->assertStatus(403);
    }

    public function test_regular_member_cannot_publish(): void
    {
        [, $capsule, $version] = $this->seedInReview();
        $this->loginAs(User::factory()->verified()->create());
        $this->publishRequest($capsule, $version)->assertStatus(403);
    }

    public function test_anonymous_cannot_publish(): void
    {
        [, $capsule, $version] = $this->seedInReview();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->publishRequest($capsule, $version)->assertUnauthorized();
    }

    public function test_unverified_moderator_cannot_publish(): void
    {
        [, $capsule, $version] = $this->seedInReview();
        $reviewer = User::factory()->unverified()->create(['role' => Role::Moderator]);
        $this->loginAs($reviewer);
        $this->publishRequest($capsule, $version)->assertStatus(403);
    }

    public function test_suspended_moderator_cannot_publish(): void
    {
        [, $capsule, $version] = $this->seedInReview();
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->loginAs($reviewer);
        User::whereKey($reviewer->id)->update(['status' => AccountStatus::Suspended->value]);
        $this->publishRequest($capsule, $version)->assertStatus(403);
    }

    /** @return iterable<string, array{CapsuleVersionState, int}> */
    public static function nonPublishableStates(): iterable
    {
        yield 'draft' => [CapsuleVersionState::Draft, 1];
        yield 'changes_requested' => [CapsuleVersionState::ChangesRequested, 2];
        yield 'published' => [CapsuleVersionState::Published, 3];
        yield 'withdrawn' => [CapsuleVersionState::Withdrawn, 4];
    }

    #[DataProvider('nonPublishableStates')]
    public function test_states_other_than_in_review_yield_409(CapsuleVersionState $state, int $lockVersion): void
    {
        [, $capsule, $version] = $this->seedInReview();
        $version->state = $state;
        $version->lock_version = $lockVersion;
        if ($state === CapsuleVersionState::Published || $state === CapsuleVersionState::Withdrawn) {
            // La contrainte SQL `capsule_versions_published_at_requires_state` impose un
            // reviewer_id et un published_at sur ces états : on complète pour pouvoir
            // sauvegarder le fixtures avant d'appeler la route.
            $version->reviewer_id = User::factory()->verified()->create(['role' => Role::Moderator])->id;
            $version->published_at = now()->utc();
        }
        $version->save();
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $response = $this->publishRequest($capsule, $version, $lockVersion);
        $response->assertStatus(409);
    }

    public function test_missing_body_or_limits_is_422_with_fields(): void
    {
        [, $capsule, $version] = $this->seedInReview();
        $version->limits = null;
        $version->body = 'trop';
        $version->save();
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $response = $this->publishRequest($capsule, $version);
        $response->assertStatus(422);
        $missing = $response->json('error.fields.content');
        $this->assertIsArray($missing);
        $this->assertContains('body', $missing);
        $this->assertContains('limits', $missing);
    }

    public function test_revoked_resolution_is_422(): void
    {
        [, $capsule, $version, $resolution] = $this->seedInReviewFromHelpRequest();
        $resolution->refresh();
        Resolution::whereKey($resolution->id)->update(['revoked_at' => now()->utc()]);
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $response = $this->publishRequest($capsule, $version);
        $response->assertStatus(422);
        $this->assertNotNull($response->json('error.fields.source_request_id'));
    }

    public function test_approved_artifact_without_notices_is_422(): void
    {
        [, $capsule, $version] = $this->seedInReview();
        Artifact::factory()->create([
            'version_id' => $version->id,
            'distribution_status' => ArtifactDistributionStatus::Approved,
            'notices_path' => null,
        ]);
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $response = $this->publishRequest($capsule, $version);
        $response->assertStatus(422);
        $this->assertNotNull($response->json('error.fields.artifacts'));
    }

    public function test_inactive_artifact_is_accepted_without_change(): void
    {
        [, $capsule, $version] = $this->seedInReview();
        $artifact = Artifact::factory()->create([
            'version_id' => $version->id,
            'distribution_status' => ArtifactDistributionStatus::Inactive,
            'notices_path' => null,
        ]);
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $this->publishRequest($capsule, $version)->assertOk();
        $artifact->refresh();
        $this->assertSame(ArtifactDistributionStatus::Inactive, $artifact->distribution_status);
        $this->assertNull($artifact->notices_path);
    }

    public function test_replay_with_same_key_yields_single_publication(): void
    {
        [, $capsule, $version] = $this->seedInReview();
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->loginAs($reviewer);
        $key = (string) Str::uuid();
        $first = $this->browserRequest('POST', $this->publishUrl($capsule, $version), ['lock_version' => 2], ['Idempotency-Key' => $key]);
        $first->assertOk();
        $second = $this->browserRequest('POST', $this->publishUrl($capsule, $version), ['lock_version' => 2], ['Idempotency-Key' => $key]);
        $second->assertOk();
        $this->assertSame($first->json('data.content_digest'), $second->json('data.content_digest'));
        $this->assertDatabaseCount('capsule_version_reviews', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
    }

    public function test_sql_direct_overwrite_of_published_body_is_refused_by_trigger(): void
    {
        [, , $version] = $this->publishedVersion();
        $this->expectExceptionMessageMatches('/capsule_versions_published_immutable/');
        DB::table('capsule_versions')->where('id', $version->id)->update(['body' => "## Procédure réécrite\n\nContenu modifié hors Service."]);
    }

    public function test_sql_direct_overwrite_of_content_digest_is_refused_by_trigger(): void
    {
        [, , $version] = $this->publishedVersion();
        $this->expectExceptionMessageMatches('/capsule_versions_published_immutable/');
        DB::table('capsule_versions')->where('id', $version->id)->update(['content_digest' => str_repeat('a', 64)]);
    }

    public function test_patch_on_published_version_is_refused_by_policy(): void
    {
        [$owner, $capsule, $version] = $this->publishedVersion();
        $this->loginAs($owner);
        $response = $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", [
            'lock_version' => $version->lock_version,
            'body' => "## Modification interdite\n\nUne version publiée est immuable.",
        ]);
        $response->assertStatus(403);
    }

    public function test_new_draft_after_publication_preserves_old_digest_and_proofs(): void
    {
        [$owner, $capsule, $publishedVersion] = $this->publishedVersion();
        $priorDigest = $publishedVersion->content_digest;
        $priorBody = $publishedVersion->body;
        Artifact::factory()->create([
            'version_id' => $publishedVersion->id,
            'distribution_status' => ArtifactDistributionStatus::Approved,
            'notices_path' => '/notices/kit.md',
        ]);
        $this->loginAs($owner);
        $response = $this->browserRequest('POST', "/api/v1/capsules/{$capsule->id}/versions", [
            'version_label' => '1.0.1',
            'body' => "## Procédure corrigée\n\nSuite au retour de la revue publique.",
            'limits' => 'Portée élargie.',
            'technologies' => [],
        ], ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertCreated();
        $publishedVersion->refresh();
        $this->assertSame($priorDigest, $publishedVersion->content_digest);
        $this->assertSame($priorBody, $publishedVersion->body);
        // La nouvelle version n'hérite d'aucune preuve.
        $this->assertSame(1, DB::table('artifacts')->where('version_id', $publishedVersion->id)->count());
        $newVersionId = $response->json('data.id');
        $this->assertNotNull($newVersionId);
        $this->assertSame(0, DB::table('artifacts')->where('version_id', $newVersionId)->count());
    }

    public function test_version_from_another_capsule_is_404(): void
    {
        [, $capsuleA, $versionA] = $this->seedInReview();
        [, $capsuleB] = $this->seedInReview(slug: 'autre-capsule-25');
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $response = $this->browserRequest('POST', "/api/v1/admin/capsules/{$capsuleB->id}/versions/{$versionA->id}/publish", ['lock_version' => 2], ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertStatus(404);
    }

    public function test_resource_does_not_expose_private_fields(): void
    {
        [, $capsule, $version] = $this->seedInReview();
        $this->loginAs(User::factory()->verified()->create(['role' => Role::Moderator]));
        $data = $this->publishRequest($capsule, $version)->json('data');
        $this->assertArrayNotHasKey('reviewer_id', $data);
        $this->assertArrayNotHasKey('reviewer_email', $data);
        $this->assertArrayNotHasKey('note', $data);
    }

    /** @return array{User, Capsule, CapsuleVersion} */
    private function seedInReview(string $slug = 'brouillon-publication'): array
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => $slug]);
        $version = $this->seedVersion($capsule, state: CapsuleVersionState::InReview, lockVersion: 2);

        return [$owner, $capsule, $version];
    }

    /** @return array{User, Capsule, CapsuleVersion, Resolution} */
    private function seedInReviewFromHelpRequest(string $slug = 'brouillon-provenance'): array
    {
        $owner = User::factory()->verified()->create();
        $request = HelpRequest::factory()->create(['author_id' => $owner->id]);
        $proposal = Proposal::factory()->create(['request_id' => $request->id, 'author_id' => $owner->id]);
        $resolution = Resolution::factory()->create(['request_id' => $request->id, 'proposal_id' => $proposal->id, 'accepted_by' => $owner->id]);
        $capsule = Capsule::factory()->create([
            'owner_id' => $owner->id,
            'slug' => $slug,
            'source_request_id' => $request->id,
            'editorial_origin' => null,
        ]);
        $version = $this->seedVersion($capsule, state: CapsuleVersionState::InReview, lockVersion: 2);

        return [$owner, $capsule, $version, $resolution];
    }

    private function seedVersion(Capsule $capsule, CapsuleVersionState $state, int $lockVersion): CapsuleVersion
    {
        return CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'body' => "## Procédure complète\n\nÉtape détaillée pour la publication documentée.",
            'limits' => 'Portée réduite au scénario cité.',
            'state' => $state,
            'lock_version' => $lockVersion,
        ]);
    }

    /** @return array{User, Capsule, CapsuleVersion} */
    private function publishedVersion(): array
    {
        [$owner, $capsule, $version] = $this->seedInReview('brouillon-publie');
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->loginAs($reviewer);
        $this->publishRequest($capsule, $version)->assertOk();
        $this->logoutBrowser();
        $version->refresh();

        return [$owner, $capsule, $version];
    }

    private function publishUrl(Capsule $capsule, CapsuleVersion $version): string
    {
        return "/api/v1/admin/capsules/{$capsule->id}/versions/{$version->id}/publish";
    }

    /** @return TestResponse<Response> */
    private function publishRequest(Capsule $capsule, CapsuleVersion $version, ?int $lockVersion = null): TestResponse
    {
        return $this->browserRequest('POST', $this->publishUrl($capsule, $version), [
            'lock_version' => $lockVersion ?? (int) $version->lock_version,
        ], ['Idempotency-Key' => (string) Str::uuid()]);
    }

    private function loginAs(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }

    private function logoutBrowser(): void
    {
        $this->browserRequest('POST', '/logout');
        $this->browserCookies = [];
    }
}
