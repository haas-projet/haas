<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Capsules\ContributionRole;
use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Infrastructure\Lab\ApprovedLabRegistry;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\HelpRequest;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_unicode_search_accepts_two_hundred_characters_and_refuses_more(): void
    {
        $query = rawurlencode(str_repeat('é', 200));
        $this->browserRequest('GET', '/api/v1/capsules?q='.$query)->assertOk()->assertJsonPath('meta.total', 0);
        $query = rawurlencode(str_repeat('é', 201));
        $this->browserRequest('GET', '/api/v1/capsules?q='.$query)->assertStatus(422);
    }

    /** @return iterable<string, array{string}> */
    public static function arrayFilters(): iterable
    {
        yield 'q list' => ['q[]=x'];
        yield 'q object' => ['q[key]=x'];
        yield 'sort list' => ['sort[]=date'];
        yield 'sort object' => ['sort[key]=relevance'];
    }

    #[DataProvider('arrayFilters')]
    public function test_array_filters_are_validation_errors_without_string_casts(string $query): void
    {
        $this->browserRequest('GET', '/api/v1/capsules?'.$query)->assertStatus(422);
    }

    public function test_detail_attributes_the_public_handle_without_legacy_private_name(): void
    {
        [$owner] = $this->publishCapsule('attribution-publique');
        $owner->forceFill(['name' => 'Nom personnel fictif privé', 'handle' => 'pseudonyme_public'])->save();
        $response = $this->browserRequest('GET', '/api/v1/capsules/attribution-publique')
            ->assertOk()->assertJsonPath('data.version.contributors.0.handle', 'pseudonyme_public');
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString('Nom personnel fictif privé', $content);
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

    public function test_legacy_published_content_digest_stays_null_on_both_detail_routes(): void
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => 'publication-historique']);
        // Fixture de lecture d'une publication antérieure à B25 : aucun digest rétroactif.
        $version = $this->seedPublishedVersion($capsule, $owner, '1.0.0', ContributionRole::Diagnosis, legacyPublication: true);

        foreach (['/api/v1/capsules/publication-historique', '/api/v1/capsules/publication-historique/versions/1.0.0'] as $path) {
            $response = $this->browserRequest('GET', $path)->assertOk();
            $this->assertArrayHasKey('content_digest', $response->json('data.version'));
            $response->assertJsonPath('data.version.content_digest', null)
                ->assertJsonPath('data.version.body', $version->body);
        }

        $this->assertNull($version->refresh()->content_digest, 'La lecture ne doit pas fabriquer une empreinte historique.');
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

    public function test_technology_filter_matches_only_the_latest_published_version(): void
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => 'technologie-courante']);
        $oldTechnology = Technology::factory()->create();
        $currentTechnology = Technology::factory()->create();
        $this->seedPublishedVersion($capsule, $owner, '1.0.0', ContributionRole::Diagnosis, now()->utc()->subDay(), [$oldTechnology]);
        $this->seedPublishedVersion($capsule, $owner, '2.0.0', ContributionRole::Fix, now()->utc(), [$currentTechnology]);

        $this->browserRequest('GET', '/api/v1/capsules?technology='.$oldTechnology->id)
            ->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        $this->browserRequest('GET', '/api/v1/capsules?technology='.$currentTechnology->id)
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.version.version_label', '2.0.0')
            ->assertJsonPath('data.0.version.technologies.0.id', $currentTechnology->id);
    }

    public function test_list_hydrates_only_one_published_version_per_card(): void
    {
        [$owner, $capsule] = $this->publishCapsule('historique-long');
        $last = null;
        for ($i = 1; $i <= 14; $i++) {
            $last = $this->seedPublishedVersion($capsule, $owner, '1.0.'.$i, ContributionRole::Fix, now()->utc()->subMinutes(14 - $i));
        }
        $retrieved = [];
        $event = 'eloquent.retrieved: '.CapsuleVersion::class;
        Event::listen($event, static function (CapsuleVersion $version) use (&$retrieved): void {
            $retrieved[] = $version->id;
        });
        try {
            $response = $this->browserRequest('GET', '/api/v1/capsules?per_page=1');
            $response->assertOk()->assertJsonPath('data.0.version.version_label', '1.0.14');
            $this->assertSame([$last->id], $retrieved, 'Une carte ne doit pas hydrater tout son historique.');
            $this->assertArrayNotHasKey('latest_published_version_id', $response->json('data.0'));
        } finally {
            Event::forget($event);
        }
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

    public function test_detail_loads_bodies_only_for_the_selected_version(): void
    {
        [$owner, $capsule] = $this->publishCapsule('historique-documentaire');
        $this->publishAdditionalVersion($capsule, $owner, '1.0.1');
        $this->publishAdditionalVersion($capsule, $owner, '1.0.2');
        $bodies = [];
        $event = 'eloquent.retrieved: '.CapsuleVersion::class;
        Event::listen($event, static function (CapsuleVersion $version) use (&$bodies): void {
            if (array_key_exists('body', $version->getAttributes())) {
                $bodies[] = $version->id;
            }
        });
        try {
            $this->browserRequest('GET', '/api/v1/capsules/historique-documentaire/versions/1.0.0')
                ->assertOk()->assertJsonCount(3, 'data.history')
                ->assertJsonPath('data.version.version_label', '1.0.0');
            $this->assertCount(1, $bodies, 'L’historique de labels ne doit pas charger les corps de toutes les versions.');
        } finally {
            Event::forget($event);
        }
    }

    public function test_search_treats_wildcards_and_sql_text_as_literal_input(): void
    {
        $this->publishCapsule('document-ordinaire');
        foreach (['%', '_', '!', "' OR 1=1 --"] as $search) {
            $this->browserRequest('GET', '/api/v1/capsules?q='.rawurlencode($search).'&sort=relevance')
                ->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        }
    }

    public function test_visibility_is_applied_before_pagination_and_repeated_reads_are_stable(): void
    {
        $this->publishCapsule('reste-visible');
        [, $hidden] = $this->publishCapsule('masquee-avant-page');
        $hidden->forceFill(['visibility' => CapsuleVisibility::Hidden])->save();
        [, , $withdrawn] = $this->publishCapsule('retiree-avant-page');
        $withdrawn->forceFill(['state' => CapsuleVersionState::Withdrawn])->save();

        $first = $this->browserRequest('GET', '/api/v1/capsules?per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.slug', 'reste-visible');
        $again = $this->browserRequest('GET', '/api/v1/capsules?per_page=1')->assertOk();
        $this->assertSame($first->json(), $again->json());
        $this->browserRequest('GET', '/api/v1/capsules?per_page=1&page=2')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonCount(0, 'data');
    }

    public function test_has_lab_filter_keeps_only_capsules_with_active_lab_on_latest_published(): void
    {
        [, , $withLabVersion] = $this->publishCapsule('capsule-avec-lab');
        $this->seedLabDefinition($withLabVersion, true);
        $this->publishCapsule('capsule-sans-lab');

        $only = $this->browserRequest('GET', '/api/v1/capsules?has_lab=true');
        $only->assertOk();
        $slugs = array_column($only->json('data'), 'slug');
        $this->assertSame(['capsule-avec-lab'], $slugs);

        $without = $this->browserRequest('GET', '/api/v1/capsules?has_lab=false');
        $without->assertOk();
        $slugsWithout = array_column($without->json('data'), 'slug');
        $this->assertSame(['capsule-sans-lab'], $slugsWithout);
    }

    public function test_has_lab_filter_ignores_disabled_definitions(): void
    {
        [, , $disabledVersion] = $this->publishCapsule('definition-desactivee');
        $this->seedLabDefinition($disabledVersion, false);
        [, , $activeVersion] = $this->publishCapsule('definition-active');
        $this->seedLabDefinition($activeVersion, true);

        $response = $this->browserRequest('GET', '/api/v1/capsules?has_lab=true');
        $response->assertOk();
        $slugs = array_column($response->json('data'), 'slug');
        $this->assertSame(['definition-active'], $slugs);
    }

    public function test_has_lab_filter_ignores_definitions_of_withdrawn_versions(): void
    {
        [, , $withdrawnVersion] = $this->publishCapsule('version-retiree-avec-lab');
        $this->seedLabDefinition($withdrawnVersion, true);
        CapsuleVersion::whereKey($withdrawnVersion->id)->update(['state' => CapsuleVersionState::Withdrawn->value]);
        [, , $publishedVersion] = $this->publishCapsule('version-active-avec-lab');
        $this->seedLabDefinition($publishedVersion, true);

        $response = $this->browserRequest('GET', '/api/v1/capsules?has_lab=true');
        $response->assertOk();
        $slugs = array_column($response->json('data'), 'slug');
        $this->assertSame(['version-active-avec-lab'], $slugs);
    }

    public function test_has_lab_filter_hides_capsules_whose_visibility_is_hidden(): void
    {
        [, $hiddenCapsule, $hiddenVersion] = $this->publishCapsule('capsule-cachee-avec-lab');
        $this->seedLabDefinition($hiddenVersion, true);
        Capsule::whereKey($hiddenCapsule->id)->update(['visibility' => CapsuleVisibility::Hidden]);
        [, , $visibleVersion] = $this->publishCapsule('capsule-visible-avec-lab');
        $this->seedLabDefinition($visibleVersion, true);

        $response = $this->browserRequest('GET', '/api/v1/capsules?has_lab=true');
        $response->assertOk();
        $slugs = array_column($response->json('data'), 'slug');
        $this->assertSame(['capsule-visible-avec-lab'], $slugs);
    }

    public function test_has_lab_filter_combines_with_technology_filter(): void
    {
        $tech = Technology::factory()->create();
        [$ownerA, $capsuleA] = $this->publishCapsule('capsule-tech-sans-lab');
        $withTechVersion = $this->seedPublishedVersion($capsuleA, $ownerA, '1.0.1', ContributionRole::Fix, now()->utc(), [$tech]);
        [$ownerB, $capsuleB] = $this->publishCapsule('capsule-tech-avec-lab');
        $withTechAndLab = $this->seedPublishedVersion($capsuleB, $ownerB, '1.0.1', ContributionRole::Fix, now()->utc(), [$tech]);
        $this->seedLabDefinition($withTechAndLab, true);
        unset($withTechVersion);

        $response = $this->browserRequest('GET', '/api/v1/capsules?technology='.$tech->id.'&has_lab=true');
        $response->assertOk();
        $slugs = array_column($response->json('data'), 'slug');
        $this->assertSame(['capsule-tech-avec-lab'], $slugs);
    }

    public function test_has_lab_filter_keeps_pagination_stable(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            [, , $version] = $this->publishCapsule(sprintf('capsule-lab-%02d', $i));
            $this->seedLabDefinition($version, true);
        }
        $this->publishCapsule('capsule-sans-lab-paginee');

        $page1 = $this->browserRequest('GET', '/api/v1/capsules?has_lab=true&per_page=2&page=1');
        $page1->assertOk()->assertJsonPath('meta.total', 3);
        $this->assertCount(2, $page1->json('data'));
        $page2 = $this->browserRequest('GET', '/api/v1/capsules?has_lab=true&per_page=2&page=2');
        $page2->assertOk()->assertJsonPath('meta.total', 3);
        $this->assertCount(1, $page2->json('data'));
        $slugsPage1 = array_column($page1->json('data'), 'slug');
        $slugsPage2 = array_column($page2->json('data'), 'slug');
        $this->assertEmpty(array_intersect($slugsPage1, $slugsPage2));
    }

    public function test_has_lab_filter_refuses_invalid_values(): void
    {
        foreach (['has_lab=1', 'has_lab=0', 'has_lab=yes', 'has_lab=TRUE', 'has_lab[]=true'] as $query) {
            $this->browserRequest('GET', '/api/v1/capsules?'.$query)->assertStatus(422);
        }
    }

    public function test_has_lab_filter_query_count_does_not_grow_with_catalogue_size(): void
    {
        $this->seedHasLabCatalogue(initial: 5);
        $firstCountsByFilter = $this->measureHasLabQueryCounts(perPage: 30);
        $this->seedHasLabCatalogue(initial: 25);
        $secondCountsByFilter = $this->measureHasLabQueryCounts(perPage: 30);
        $this->assertSame(
            $firstCountsByFilter['true'],
            $secondCountsByFilter['true'],
            'Le filtre has_lab=true ne doit pas introduire de requêtes supplémentaires avec la taille du catalogue.'
        );
        $this->assertSame(
            $firstCountsByFilter['false'],
            $secondCountsByFilter['false'],
            'Le filtre has_lab=false ne doit pas introduire de requêtes supplémentaires avec la taille du catalogue.'
        );
    }

    public function test_pagination_bounds_and_foreign_version_labels_are_refused(): void
    {
        foreach (['page=0', 'page=2147483648', 'page[]=1', 'per_page=0', 'per_page=51', 'technology[]=x'] as $query) {
            $this->browserRequest('GET', '/api/v1/capsules?'.$query)->assertStatus(422);
        }
        [$owner, $other] = $this->publishCapsule('autre-capsule');
        $this->publishAdditionalVersion($other, $owner, '9.0.0');
        $this->publishCapsule('capsule-demandee');
        $this->browserRequest('GET', '/api/v1/capsules/capsule-demandee/versions/9.0.0')->assertNotFound();
    }

    public function test_equal_publication_dates_have_deterministic_capsule_and_version_order(): void
    {
        $publishedAt = now()->utc()->subHour();
        $capsules = [];
        foreach (['egalite-a', 'egalite-b', 'egalite-c'] as $slug) {
            $owner = User::factory()->verified()->create();
            $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => $slug]);
            $firstVersion = $this->seedPublishedVersion($capsule, $owner, '1.0.0', ContributionRole::Diagnosis, $publishedAt);
            $secondVersion = $this->seedPublishedVersion($capsule, $owner, '2.0.0', ContributionRole::Fix, $publishedAt);
            $expectedVersion = strcmp($firstVersion->id, $secondVersion->id) < 0 ? '1.0.0' : '2.0.0';
            $capsules[$capsule->id] = [$slug, $expectedVersion];
        }
        ksort($capsules);
        foreach (array_values($capsules) as $index => [$slug, $label]) {
            $this->browserRequest('GET', '/api/v1/capsules?per_page=1&page='.($index + 1))
                ->assertOk()->assertJsonPath('data.0.slug', $slug)
                ->assertJsonPath('data.0.version.version_label', $label);
            $this->browserRequest('GET', '/api/v1/capsules/'.$slug)->assertOk()
                ->assertJsonPath('data.version.version_label', $label);
        }
    }

    public function test_documentary_capsule_stays_independent_without_exposing_hidden_source_or_account(): void
    {
        $owner = User::factory()->verified()->create();
        $source = HelpRequest::factory()->create([
            'author_id' => $owner->id,
            'state' => HelpRequestState::Resolved,
            'title' => 'Titre fictif réservé de la demande source',
        ]);
        $capsule = Capsule::factory()->fromResolvedRequest($source->id)
            ->create(['owner_id' => $owner->id, 'slug' => 'document-independant']);
        // Fixture de lecture uniquement : ceci ne reçoit pas la publication du Service B25.
        $this->seedPublishedVersion($capsule, $owner, '1.0.0', ContributionRole::Diagnosis);
        $source->forceFill(['hidden_at' => now()->utc()])->save();
        $owner->forceFill(['status' => AccountStatus::Suspended, 'email_verified_at' => null])->save();

        $this->browserRequest('GET', '/api/v1/capsules')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.source.kind', 'help_request');
        $detail = $this->browserRequest('GET', '/api/v1/capsules/document-independant')->assertOk();
        $this->assertSame(['kind' => 'help_request', 'editorial_origin' => null], $detail->json('data.source'));
        $body = $detail->getContent();
        $this->assertIsString($body);
        foreach ([$source->id, $source->title, $owner->email] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $body);
        }
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

    /** @param list<Technology> $technologies */
    private function seedPublishedVersion(Capsule $capsule, User $owner, string $label, ContributionRole $role, ?\DateTimeInterface $publishedAt = null, array $technologies = [], bool $legacyPublication = false): CapsuleVersion
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
        foreach ($technologies as $technology) {
            DB::table('capsule_version_technologies')->insert([
                'version_id' => $version->id,
                'technology_id' => $technology->id,
                'version_label' => '^1.0',
                'created_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);
        }
        // Horodatages déterministes : 1.0.0 est plus ancien, 1.0.1 plus récent.
        $effectivePublishedAt = $publishedAt ?? now()->utc()->subDay();
        CapsuleVersion::whereKey($version->id)->update([
            'state' => CapsuleVersionState::Published->value,
            'reviewer_id' => $moderator->id,
            'published_at' => $effectivePublishedAt,
            'content_digest' => $legacyPublication ? null : hash('sha256', $capsule->slug.' / '.$label),
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

    private function seedLabDefinition(CapsuleVersion $version, bool $enabled): string
    {
        $id = (string) Str::uuid();
        DB::connection('pgsql')->table('lab_definitions')->insert(array_replace([
            'id' => $id,
            'version_id' => $version->id,
            ...(new ApprovedLabRegistry)->manifest(),
            'version_digest' => $version->content_digest ?? hash('sha256', 'q16-fixture-'.$version->id),
            'enabled' => $enabled,
            'created_at' => now()->utc(),
        ], []));

        return $id;
    }

    private function seedHasLabCatalogue(int $initial): void
    {
        $existing = Capsule::query()->count();
        for ($i = 1; $i <= $initial; $i++) {
            $index = $existing + $i;
            $slug = sprintf('nplus1-%04d', $index);
            [, , $version] = $this->publishCapsule($slug);
            if ($index % 2 === 0) {
                $this->seedLabDefinition($version, true);
            }
        }
    }

    /** @return array{true: int, false: int} */
    private function measureHasLabQueryCounts(int $perPage): array
    {
        DB::enableQueryLog();
        try {
            $counts = [];
            foreach (['true', 'false'] as $value) {
                DB::flushQueryLog();
                $this->browserRequest('GET', '/api/v1/capsules?has_lab='.$value.'&per_page='.$perPage)->assertOk();
                $counts[$value] = count(array_filter(
                    DB::getQueryLog(),
                    fn (array $query): bool => str_starts_with($query['query'], 'select') && ! str_contains($query['query'], '"sessions"'),
                ));
            }
        } finally {
            DB::disableQueryLog();
        }

        /** @var array{true: int, false: int} $counts */
        return $counts;
    }
}
