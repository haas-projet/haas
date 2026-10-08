<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Capsules\ContributionRole;
use App\Enums\Identity\Role;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\SpaHttpRequests;
use Tests\Support\TestDatabaseGuard;

/**
 * Contrats HTTP du catalogue public (B26). Visiteurs et membres voient
 * la même liste ; brouillons, revues et retraits restent invisibles.
 */
final class CapsuleCatalogueHttpTest extends PostgresTestCase
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

    public function test_visitor_and_member_see_the_same_catalogue(): void
    {
        $this->publishCapsule('version-publique');
        $visitorList = $this->browserRequest('GET', '/api/v1/capsules');
        $visitorList->assertOk();
        $slugs = array_column($visitorList->json('data'), 'slug');
        $this->assertSame(['version-publique'], $slugs);

        $this->loginAs(User::factory()->verified()->create());
        $memberList = $this->browserRequest('GET', '/api/v1/capsules');
        $memberList->assertOk();
        $this->assertSame($slugs, array_column($memberList->json('data'), 'slug'));
    }

    public function test_only_published_versions_are_listed(): void
    {
        $draftCapsule = Capsule::factory()->create(['slug' => 'brouillon-non-liste']);
        CapsuleVersion::factory()->create(['capsule_id' => $draftCapsule->id, 'state' => CapsuleVersionState::Draft, 'lock_version' => 1]);

        $reviewCapsule = Capsule::factory()->create(['slug' => 'revue-non-liste']);
        CapsuleVersion::factory()->create(['capsule_id' => $reviewCapsule->id, 'state' => CapsuleVersionState::InReview, 'lock_version' => 2]);

        $this->publishCapsule('version-reelle');

        $response = $this->browserRequest('GET', '/api/v1/capsules');
        $response->assertOk();
        $slugs = array_column($response->json('data'), 'slug');
        $this->assertSame(['version-reelle'], $slugs);
    }

    public function test_hidden_capsule_is_absent_from_list_and_detail(): void
    {
        [, , $version] = $this->publishCapsule('capsule-masquee');
        Capsule::whereKey($version->capsule_id)->update(['visibility' => CapsuleVisibility::Hidden]);

        $list = $this->browserRequest('GET', '/api/v1/capsules');
        $list->assertOk();
        $this->assertEmpty($list->json('data'));

        $detail = $this->browserRequest('GET', '/api/v1/capsules/capsule-masquee');
        $detail->assertNotFound();

        $direct = $this->browserRequest('GET', '/api/v1/capsules/capsule-masquee/versions/1.0.0');
        $direct->assertNotFound();
    }

    public function test_withdrawn_version_disappears_from_list_search_and_detail(): void
    {
        [, $capsule, $version] = $this->publishCapsule('a-retirer');
        // Transition en retrait sans passer par un Service public (test de visibilité uniquement).
        CapsuleVersion::whereKey($version->id)->update(['state' => CapsuleVersionState::Withdrawn->value]);
        // Rendre la capsule orpheline (aucune autre version publiée) et vérifier qu'elle disparaît.
        $noPublishedList = $this->browserRequest('GET', '/api/v1/capsules?q=a-retirer');
        $noPublishedList->assertOk();
        $this->assertEmpty($noPublishedList->json('data'));

        $detail = $this->browserRequest('GET', '/api/v1/capsules/a-retirer');
        $detail->assertNotFound();

        $direct = $this->browserRequest('GET', "/api/v1/capsules/a-retirer/versions/{$version->version_label}");
        $direct->assertNotFound();
    }

    public function test_previously_published_version_remains_visible_when_still_published(): void
    {
        [$owner, $capsule, $v100] = $this->publishCapsule('plusieurs-versions');
        $v101 = $this->publishAdditionalVersion($capsule, $owner, '1.0.1');

        $defaultDetail = $this->browserRequest('GET', '/api/v1/capsules/plusieurs-versions');
        $defaultDetail->assertOk();
        $this->assertSame('1.0.1', $defaultDetail->json('data.version.version_label'));
        $historyLabels = array_column($defaultDetail->json('data.history'), 'version_label');
        $this->assertSame(['1.0.1', '1.0.0'], $historyLabels);

        $priorDetail = $this->browserRequest('GET', '/api/v1/capsules/plusieurs-versions/versions/1.0.0');
        $priorDetail->assertOk();
        $this->assertSame('1.0.0', $priorDetail->json('data.version.version_label'));
    }

    public function test_technology_filter_isolates_matching_versions(): void
    {
        $techA = Technology::factory()->create();
        $techB = Technology::factory()->create();
        [, $capsuleA, $versionA] = $this->seedDraftCapsuleForAttachments('version-tech-a');
        DB::table('capsule_version_technologies')->insert([
            'version_id' => $versionA->id,
            'technology_id' => $techA->id,
            'version_label' => '^1.0',
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        $this->publishDraftVersion($versionA);
        [, $capsuleB, $versionB] = $this->seedDraftCapsuleForAttachments('version-tech-b');
        DB::table('capsule_version_technologies')->insert([
            'version_id' => $versionB->id,
            'technology_id' => $techB->id,
            'version_label' => '^2.0',
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        $this->publishDraftVersion($versionB);

        $filtered = $this->browserRequest('GET', "/api/v1/capsules?technology={$techA->id}");
        $filtered->assertOk();
        $slugs = array_column($filtered->json('data'), 'slug');
        $this->assertSame(['version-tech-a'], $slugs);
    }

    public function test_arbitrary_sort_and_oversized_page_are_refused(): void
    {
        $this->browserRequest('GET', '/api/v1/capsules?sort=arbitraire')->assertStatus(422);
        $this->browserRequest('GET', '/api/v1/capsules?per_page=51')->assertStatus(422);
        $this->browserRequest('GET', '/api/v1/capsules?sort=relevance')->assertStatus(422); // relevance sans q
        $this->browserRequest('GET', '/api/v1/capsules?inconnu=1')->assertStatus(422);
    }

    public function test_pagination_is_stable_across_pages(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->publishCapsule(sprintf('publication-%02d', $i));
        }
        $page1 = $this->browserRequest('GET', '/api/v1/capsules?per_page=2&page=1');
        $page1->assertOk();
        $this->assertSame(2, count($page1->json('data')));
        $firstPageSlugs = array_column($page1->json('data'), 'slug');

        $page2 = $this->browserRequest('GET', '/api/v1/capsules?per_page=2&page=2');
        $page2->assertOk();
        $this->assertSame(1, count($page2->json('data')));
        $secondPageSlugs = array_column($page2->json('data'), 'slug');

        $this->assertEmpty(array_intersect($firstPageSlugs, $secondPageSlugs));
    }

    public function test_empty_result_returns_empty_list_never_fabricated(): void
    {
        $response = $this->browserRequest('GET', '/api/v1/capsules');
        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_response_does_not_expose_private_fields(): void
    {
        [$owner, , $version] = $this->publishCapsule('verifier-fuite');
        $list = $this->browserRequest('GET', '/api/v1/capsules')->json('data.0');
        foreach (['reviewer_id', 'owner_id', 'source_request_id', 'private_path', 'notices_path'] as $leak) {
            $this->assertArrayNotHasKey($leak, $list);
        }
        $this->assertArrayNotHasKey('email', $list['source'] ?? []);
        $this->assertArrayNotHasKey('private_path', $list['version'] ?? []);

        $detail = $this->browserRequest('GET', '/api/v1/capsules/verifier-fuite')->json('data');
        foreach (['reviewer_id', 'owner_id', 'source_request_id'] as $leak) {
            $this->assertArrayNotHasKey($leak, $detail);
            $this->assertArrayNotHasKey($leak, $detail['version'] ?? []);
        }
        foreach ($detail['version']['contributors'] as $contributor) {
            $this->assertArrayHasKey('handle', $contributor);
            $this->assertArrayHasKey('role', $contributor);
            $this->assertArrayNotHasKey('email', $contributor);
            $this->assertArrayNotHasKey('user_id', $contributor);
        }
    }

    public function test_detail_includes_contributors_and_technologies(): void
    {
        [$owner, $capsule, $version] = $this->seedDraftCapsuleForAttachments('detail-complet');
        $tech = Technology::factory()->create();
        DB::table('capsule_version_technologies')->insert([
            'version_id' => $version->id,
            'technology_id' => $tech->id,
            'version_label' => '^1.0',
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        $extra = User::factory()->verified()->create();
        DB::table('capsule_contributors')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => $version->id,
            'user_id' => $extra->id,
            'contribution_role' => ContributionRole::Documentation->value,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        $this->publishDraftVersion($version);
        $detail = $this->browserRequest('GET', '/api/v1/capsules/detail-complet');
        $detail->assertOk();
        $this->assertSame('1.0.0', $detail->json('data.version.version_label'));
        $this->assertNotNull($detail->json('data.version.content_digest'));
        $contributors = $detail->json('data.version.contributors');
        $this->assertNotEmpty($contributors);
        $roles = array_column($contributors, 'role');
        $this->assertContains(ContributionRole::Documentation->value, $roles);
        $this->assertCount(1, $detail->json('data.version.technologies'));
    }

    public function test_catalogue_has_no_custom_headers(): void
    {
        $this->publishCapsule('entetes-neutres');
        $response = $this->browserRequest('GET', '/api/v1/capsules');
        $response->assertOk();
        $headers = $response->headers->all();
        $this->assertArrayNotHasKey('x-capsule-owner', $headers);
        $this->assertArrayNotHasKey('x-capsule-reviewer', $headers);
    }

    public function test_list_without_n_plus_one_on_thirty_capsules(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->publishCapsule(sprintf('n-plus-un-%02d', $i));
        }
        DB::enableQueryLog();
        $response = $this->browserRequest('GET', '/api/v1/capsules?per_page=30');
        $response->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        // Préchargement : capsules + sous-select pour la dernière publication +
        // chargement de latestPublished + technologies + sessions SPA. Un ordre
        // de grandeur fixe (< 30) suffit à prouver l'absence de N+1.
        $this->assertLessThan(30, count($queries), 'Trop de requêtes : préchargement cassé.');
        $this->assertSame(30, count($response->json('data')));
    }

    public function test_direct_slug_on_capsule_without_published_version_is_404(): void
    {
        $capsule = Capsule::factory()->create(['slug' => 'brouillon-privee']);
        CapsuleVersion::factory()->create(['capsule_id' => $capsule->id, 'state' => CapsuleVersionState::Draft, 'lock_version' => 1]);
        $response = $this->browserRequest('GET', '/api/v1/capsules/brouillon-privee');
        $response->assertNotFound();
    }

    public function test_relevance_sort_prioritises_slug_matches(): void
    {
        $this->publishCapsule('cible-exacte-recherchee');
        $this->publishCapsule('autre-contenu-divers');
        // La recherche "exacte" ne matche que la première.
        $relevant = $this->browserRequest('GET', '/api/v1/capsules?q=exacte&sort=relevance');
        $relevant->assertOk();
        $slugs = array_column($relevant->json('data'), 'slug');
        $this->assertSame(['cible-exacte-recherchee'], $slugs);
    }

    /** @return array{User, Capsule, CapsuleVersion} */
    private function publishCapsule(string $slug): array
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => $slug]);
        $version = $this->seedPublishedVersion($capsule, $owner, '1.0.0', ContributionRole::Diagnosis);

        return [$owner, $capsule, $version];
    }

    private function publishAdditionalVersion(Capsule $capsule, User $owner, string $label): CapsuleVersion
    {
        return $this->seedPublishedVersion($capsule, $owner, $label, ContributionRole::Fix, now()->utc());
    }

    /** @return array{User, Capsule, CapsuleVersion} */
    private function seedDraftCapsuleForAttachments(string $slug): array
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => $slug]);
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'body' => "## Procédure\n\nÉtape clairement décrite pour les pièces jointes.",
            'limits' => 'Portée réduite au scénario cité.',
            'state' => CapsuleVersionState::Draft,
            'lock_version' => 1,
        ]);
        DB::table('capsule_contributors')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => $version->id,
            'user_id' => $owner->id,
            'contribution_role' => ContributionRole::Diagnosis->value,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return [$owner, $capsule, $version];
    }

    private function publishDraftVersion(CapsuleVersion $version): void
    {
        $moderator = User::factory()->verified()->create(['role' => Role::Moderator]);
        CapsuleVersion::whereKey($version->id)->update([
            'state' => CapsuleVersionState::Published->value,
            'reviewer_id' => $moderator->id,
            'published_at' => now()->utc(),
            'content_digest' => hash('sha256', $version->id.' / '.$version->version_label),
            'lock_version' => $version->lock_version + 1,
        ]);
        $version->refresh();
    }

    private function seedPublishedVersion(Capsule $capsule, User $owner, string $label, ContributionRole $role, ?\DateTimeInterface $publishedAt = null): CapsuleVersion
    {
        $moderator = User::factory()->verified()->create(['role' => Role::Moderator]);
        // Création en brouillon : le trigger B22 n'interdit les écritures sur
        // `capsule_contributors`/`capsule_version_technologies` que pour les
        // versions `published_at IS NOT NULL`.
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'version_label' => $label,
            'body' => "## Procédure\n\nÉtape clairement décrite pour le catalogue ({$label}).",
            'limits' => 'Portée réduite au scénario cité.',
            'state' => CapsuleVersionState::Draft,
            'lock_version' => 1,
        ]);
        DB::table('capsule_contributors')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => $version->id,
            'user_id' => $owner->id,
            'contribution_role' => $role->value,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        // Horodatages déterministes : 1.0.0 est plus ancien, 1.0.1 plus récent.
        $effectivePublishedAt = $publishedAt ?? now()->utc()->subDay();
        CapsuleVersion::whereKey($version->id)->update([
            'state' => CapsuleVersionState::Published->value,
            'reviewer_id' => $moderator->id,
            'published_at' => $effectivePublishedAt,
            'content_digest' => hash('sha256', $capsule->slug.' / '.$label),
            'lock_version' => 2,
        ]);
        $version->refresh();

        return $version;
    }

    private function loginAs(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}
