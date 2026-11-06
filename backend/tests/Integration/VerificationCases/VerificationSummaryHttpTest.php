<?php

declare(strict_types=1);

namespace Tests\Integration\VerificationCases;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Capsules\ContributionRole;
use App\Enums\Comparisons\ComparisonSide;
use App\Enums\Lab\LabRunState;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleContributor;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Services\Comparisons\ReconcileComparisonsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\Comparisons\ComparisonRunFixtures;
use Tests\Support\Comparisons\LabResultFixtures;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\SpaHttpRequests;

/**
 * BV209 — Fiche de vérification versionnée. Publique, anonyme et membre
 * voient la même fiche. Blocs DISTINCTS : jamais de score agrégé, jamais
 * de badge, jamais de réussite héritée d'une autre version. Aucun
 * reviewer_id, note de revue, courriel, chemin privé d'artefact ni
 * empreinte de notice.
 */
final class VerificationSummaryHttpTest extends PostgresTestCase
{
    use ComparisonRunFixtures, DatabaseMigrations, LabResultFixtures, RegistryFixtures, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    protected function tearDown(): void
    {
        try {
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_route_rejects_any_query_parameter(): void
    {
        $version = $this->publishedVersion();
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/verification-summary?foo=bar');
        $response->assertStatus(422);
    }

    public function test_unknown_uuid_returns_not_found(): void
    {
        $response = $this->browserRequest('GET', '/api/v1/versions/00000000-0000-4000-8000-000000000bad/verification-summary');
        $response->assertNotFound();
    }

    public function test_draft_version_returns_not_found(): void
    {
        $capsule = Capsule::factory()->create();
        $version = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'state' => CapsuleVersionState::Draft,
        ]);
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/verification-summary');
        $response->assertNotFound();
    }

    public function test_hidden_capsule_returns_not_found_even_with_published_version(): void
    {
        $version = $this->publishedVersion();
        Capsule::query()->whereKey($version->capsule_id)->update(['visibility' => CapsuleVisibility::Hidden->value]);
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/verification-summary');
        $response->assertNotFound();
    }

    public function test_anonymous_and_member_see_the_same_sheet(): void
    {
        $contributor = User::factory()->verified()->create();
        $version = $this->publishedVersionWith(function (CapsuleVersion $draft) use ($contributor): void {
            $this->attachContributor($draft, $contributor, ContributionRole::Fix);
        });

        $anonymous = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/verification-summary');
        $anonymous->assertOk();
        $this->assertSame('no-store, private', $anonymous->headers->get('Cache-Control'));

        $member = User::factory()->verified()->create();
        $this->loginAs($member);
        $withSession = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/verification-summary');
        $withSession->assertOk();

        $this->assertSame($anonymous->json('data'), $withSession->json('data'));
    }

    public function test_complete_sheet_lists_all_blocks_without_internal_fields(): void
    {
        $contributor = User::factory()->verified()->create(['handle' => 'contributrice-sofia']);
        $version = $this->publishedVersionWith(function (CapsuleVersion $draft) use ($contributor): void {
            $this->attachContributor($draft, $contributor, ContributionRole::Diagnosis);
        });
        $reviewer = User::factory()->verified()->create(['handle' => 'relecteur-paul']);
        $reporter = User::factory()->verified()->create(['handle' => 'observatrice-nina']);
        $this->seedIntegratedCase($version, $reviewer);
        $this->seedReuseReport($version, $reporter, isInternal: false);

        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/verification-summary');
        $response->assertOk();
        $payload = $response->json('data');

        $this->assertSame('published', $payload['version']['state']);
        $this->assertSame($version->version_label, $payload['version']['version_label']);

        // Contributeurs : handle seul, aucun id, courriel ou identifiant interne.
        $this->assertCount(1, $payload['contributors']);
        $this->assertSame(['handle' => 'contributrice-sofia', 'role' => 'diagnosis'], $payload['contributors'][0]);

        // Cas intégrés : pseudo du relecteur SEULEMENT (AC46), scenario, digest, release, source=ci.
        $this->assertCount(1, $payload['cases_examined']);
        $this->assertSame('relecteur-paul', $payload['cases_examined'][0]['reviewer_handle']);
        $this->assertSame('ci', $payload['cases_examined'][0]['source']);
        $this->assertArrayNotHasKey('reviewer_id', $payload['cases_examined'][0]);
        $this->assertArrayNotHasKey('note', $payload['cases_examined'][0]);
        $this->assertArrayNotHasKey('review_note', $payload['cases_examined'][0]);

        // Observations : source=human_declaration, is_internal préservé.
        $this->assertCount(1, $payload['observations_declared']);
        $this->assertSame('observatrice-nina', $payload['observations_declared'][0]['handle']);
        $this->assertFalse($payload['observations_declared'][0]['is_internal']);
        $this->assertSame('human_declaration', $payload['observations_declared'][0]['source']);
        $this->assertArrayNotHasKey('email', $payload['observations_declared'][0]);
        $this->assertArrayNotHasKey('user_id', $payload['observations_declared'][0]);

        // Lab : worker non livré, aucun lab_result réel -> message explicite.
        $this->assertFalse($payload['lab_evidence']['available']);
        $this->assertSame('Aucun test de laboratoire disponible pour cette version', $payload['lab_evidence']['message']);
        $this->assertSame([], $payload['lab_evidence']['runs']);
        $this->assertSame([], $payload['lab_evidence']['comparisons']);
    }

    public function test_version_without_lab_results_shows_unavailable_block(): void
    {
        $version = $this->publishedVersion();
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/verification-summary');
        $response->assertOk();
        $this->assertFalse($response->json('data.lab_evidence.available'));
        $this->assertSame('Aucun test de laboratoire disponible pour cette version', $response->json('data.lab_evidence.message'));
    }

    public function test_lab_evidence_block_lists_runs_and_comparisons_when_results_exist(): void
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $definitionId = $this->definition($version);
        $actor = User::factory()->verified()->create();
        $comparison = $this->pendingComparisonRun($profileId, $actor);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Baseline, $version);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Candidate, $version);
        $this->recordTerminalResult($comparison, ComparisonSide::Baseline, LabRunState::Failed, ['a' => false]);
        $this->recordTerminalResult($comparison, ComparisonSide::Candidate, LabRunState::Passed, ['a' => true]);
        app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/verification-summary');
        $response->assertOk();
        $block = $response->json('data.lab_evidence');
        $this->assertTrue($block['available']);
        $this->assertNull($block['message']);
        $this->assertCount(2, $block['runs']);
        $this->assertSame('lab', $block['runs'][0]['source']);
        $this->assertCount(1, $block['comparisons']);
        $this->assertSame('completed', $block['comparisons'][0]['state']);
        $this->assertSame('improved', $block['comparisons'][0]['outcome']);
        $this->assertSame('lab', $block['comparisons'][0]['source']);
    }

    public function test_new_version_does_not_inherit_badge_or_evidence_from_a_previous_version(): void
    {
        $capsule = Capsule::factory()->create();
        $moderator = User::factory()->verified()->moderator()->create();
        $v1 = CapsuleVersion::factory()->published($moderator)->create([
            'capsule_id' => $capsule->id,
            'version_label' => '1.0.0',
            'content_digest' => hash('sha256', 'bv209 v1 '.$capsule->id),
        ]);
        $v2 = CapsuleVersion::factory()->published($moderator)->create([
            'capsule_id' => $capsule->id,
            'version_label' => '2.0.0',
            'content_digest' => hash('sha256', 'bv209 v2 '.$capsule->id),
        ]);
        $reporter = User::factory()->verified()->create();
        $this->seedReuseReport($v1, $reporter, isInternal: false);
        $reviewer = User::factory()->verified()->create();
        $this->seedIntegratedCase($v1, $reviewer);

        $responseV1 = $this->browserRequest('GET', '/api/v1/versions/'.$v1->id.'/verification-summary');
        $responseV1->assertOk();
        $this->assertCount(1, $responseV1->json('data.cases_examined'));
        $this->assertCount(1, $responseV1->json('data.observations_declared'));

        $responseV2 = $this->browserRequest('GET', '/api/v1/versions/'.$v2->id.'/verification-summary');
        $responseV2->assertOk();
        $this->assertCount(0, $responseV2->json('data.cases_examined'));
        $this->assertCount(0, $responseV2->json('data.observations_declared'));
        $this->assertFalse($responseV2->json('data.lab_evidence.available'));
    }

    public function test_auto_validation_is_marked_internal_and_not_counted_as_independent(): void
    {
        $version = $this->publishedVersion();
        $capsule = Capsule::query()->findOrFail($version->capsule_id);
        $owner = User::query()->findOrFail($capsule->owner_id);
        $this->seedReuseReport($version, $owner, isInternal: true);

        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/verification-summary');
        $response->assertOk();
        $items = $response->json('data.observations_declared');
        $this->assertCount(1, $items);
        $this->assertTrue($items[0]['is_internal']);
        // L'auteur apparaît marqué `is_internal` — « non comptée » signifie
        // qu'aucune agrégation ne la convertit en score (nous n'exposons
        // volontairement aucun total, aucun badge, aucune « fiabilité »).
        foreach (['score', 'badge', 'reliability', 'fiabilite', 'validations_count'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $response->json('data'));
        }
    }

    public function test_no_private_field_leaks_anywhere_in_the_payload(): void
    {
        $version = $this->publishedVersion();
        $reviewer = User::factory()->verified()->create();
        $reporter = User::factory()->verified()->create();
        $this->seedIntegratedCase($version, $reviewer);
        $this->seedReuseReport($version, $reporter, isInternal: false);
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/verification-summary');
        $response->assertOk();
        $raw = (string) $response->getContent();
        foreach (['reviewer_id', 'review_note', '"notes"', 'email', 'private_path', 'notices_path'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $raw, 'Champ privé exposé : '.$forbidden);
        }
    }

    public function test_query_count_is_independent_of_contributor_and_declaration_volume(): void
    {
        $small = $this->publishedVersionWith(function (CapsuleVersion $draft): void {
            for ($i = 0; $i < 5; $i++) {
                $this->attachContributor($draft, User::factory()->verified()->create(), ContributionRole::Documentation);
            }
        });
        for ($i = 0; $i < 5; $i++) {
            $this->seedReuseReport($small, User::factory()->verified()->create(), isInternal: false);
        }
        $queriesSmall = $this->countQueriesFor('/api/v1/versions/'.$small->id.'/verification-summary');

        $large = $this->publishedVersionWith(function (CapsuleVersion $draft): void {
            for ($i = 0; $i < 30; $i++) {
                $this->attachContributor($draft, User::factory()->verified()->create(), ContributionRole::Documentation);
            }
        });
        for ($i = 0; $i < 30; $i++) {
            $this->seedReuseReport($large, User::factory()->verified()->create(), isInternal: false);
        }
        $queriesLarge = $this->countQueriesFor('/api/v1/versions/'.$large->id.'/verification-summary');

        $this->assertSame($queriesSmall, $queriesLarge, "N+1 détecté : {$queriesSmall} vs {$queriesLarge}.");
    }

    public function test_route_is_read_only_no_mutation_registered(): void
    {
        $writers = ['POST', 'PATCH', 'PUT', 'DELETE'];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = (string) $route->uri();
            if (! preg_match('#api/v1/versions/[^/]+/verification-summary#', $uri)) {
                continue;
            }
            foreach ($route->methods() as $method) {
                $this->assertNotContains(strtoupper($method), $writers, 'BV209 est lecture seule : '.$method.' '.$uri);
            }
        }
    }

    private function countQueriesFor(string $uri): int
    {
        // Un premier appel « à vide » amorce le middleware/API (chargement
        // du schéma de session, résolution du routeur, warmup du connecteur
        // PDO), afin que le compteur ne mesure que les SELECTs métier.
        $this->browserRequest('GET', $uri)->assertOk();
        DB::connection('pgsql')->flushQueryLog();
        DB::connection('pgsql')->enableQueryLog();
        try {
            $this->browserRequest('GET', $uri)->assertOk();

            return count(DB::connection('pgsql')->getQueryLog());
        } finally {
            DB::connection('pgsql')->disableQueryLog();
            DB::connection('pgsql')->flushQueryLog();
        }
    }

    /**
     * Les triggers B22 (b22_guard_capsule_attachment) interdisent d'insérer un
     * contributeur sur une version déjà publiée. On passe donc par un draft,
     * on branche les contributeurs, puis on publie via UPDATE (OLD.published_at
     * NULL -> UPDATE autorisé, b22 n'empêche que la mutation après publication).
     * Le relecteur doit être distinct de l'owner et des contributeurs
     * (contrainte b22_guard_capsule_version `capsule_versions_independent_reviewer`).
     */
    private function publishedVersionWith(callable $prepareDraft): CapsuleVersion
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id]);
        $draft = CapsuleVersion::factory()->create([
            'capsule_id' => $capsule->id,
            'state' => CapsuleVersionState::Draft,
            'reviewer_id' => null,
            'published_at' => null,
            'version_label' => '1.0.0',
            'content_digest' => null,
        ]);
        $prepareDraft($draft);
        $reviewer = User::factory()->verified()->moderator()->create();
        DB::connection('pgsql')->table('capsule_versions')
            ->where('id', $draft->id)
            ->update([
                'state' => CapsuleVersionState::Published->value,
                'reviewer_id' => $reviewer->id,
                'published_at' => now()->utc(),
                'content_digest' => hash('sha256', 'bv209 fixture '.$draft->id),
                'updated_at' => now()->utc(),
            ]);

        return CapsuleVersion::query()->findOrFail($draft->id);
    }

    private function attachContributor(CapsuleVersion $version, User $user, ContributionRole $role): void
    {
        $contributor = new CapsuleContributor;
        $contributor->forceFill([
            'id' => (string) Str::uuid(),
            'version_id' => $version->id,
            'user_id' => $user->id,
            'contribution_role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();
    }

    private function seedIntegratedCase(CapsuleVersion $version, User $reviewer): void
    {
        $author = User::factory()->verified()->create();
        $case = new VerificationCase;
        $case->forceFill([
            'id' => (string) Str::uuid(),
            'author_id' => $author->id,
            'capsule_version_id' => $version->id,
            'state' => VerificationCaseState::Integrated,
            'current_revision' => 1,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ])->save();
        $revisionId = (string) Str::uuid();
        $digest = str_repeat('a', 64);
        DB::connection('pgsql')->table('verification_case_revisions')->insert([
            'id' => $revisionId,
            'case_id' => $case->id,
            'revision_no' => 1,
            'body' => json_encode(['step' => 'démonstration pédagogique'], JSON_THROW_ON_ERROR),
            'digest' => $digest,
            'submitted_at' => now()->utc(),
            'created_at' => now()->utc()->subMinute(),
            'updated_at' => now()->utc(),
        ]);
        DB::connection('pgsql')->table('case_scenario_links')->insert([
            'id' => (string) Str::uuid(),
            'case_revision_id' => $revisionId,
            'scenario_key' => 'nominal_event',
            'suite_digest' => str_repeat('b', 64),
            'release_commit' => str_repeat('c', 40),
            'reviewer_id' => $reviewer->id,
            'integrated_at' => now()->utc(),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
    }

    private function seedReuseReport(CapsuleVersion $version, User $user, bool $isInternal): void
    {
        DB::connection('pgsql')->table('reuse_reports')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'version_id' => $version->id,
            'environment' => 'laragon-local',
            'reported_at' => CarbonImmutable::now('UTC')->toDateString(),
            'outcome' => 'reproduced',
            'observation' => 'Observation synthétique de démonstration pédagogique.',
            'is_internal' => $isInternal,
            'revision' => 1,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
    }

    private function loginAs(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}
