<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use App\Enums\Comparisons\ComparisonSide;
use App\Enums\Lab\LabRunState;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Comparisons\ComparisonRun;
use App\Models\User;
use App\Services\Comparisons\ReconcileComparisonsService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Route;
use Tests\PostgresTestCase;
use Tests\Support\Comparisons\ComparisonRunFixtures;
use Tests\Support\Comparisons\LabResultFixtures;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\SpaHttpRequests;

/**
 * BV208 — GET /api/v1/comparisons/{id} après réconciliation. Le propriétaire
 * reçoit outcome dérivé (jamais assertions, runtime, digest brut). Toute
 * autre id renvoie 404. Les routes mutantes de /comparisons/* restent
 * interdites (BV208 n'écrit via aucune route publique).
 */
final class ComparisonOutcomeExposureHttpTest extends PostgresTestCase
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

    public function test_owner_sees_improved_outcome_without_assertions_or_runtime_or_digests(): void
    {
        [$actor, $comparison] = $this->setupImprovedComparison();
        $this->loginAs($actor);
        $response = $this->browserRequest('GET', '/api/v1/comparisons/'.$comparison->id);

        $response->assertOk();
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $payload = $response->json('data');
        $this->assertSame('completed', $payload['state']);
        $this->assertSame('improved', $payload['outcome']);
        foreach (['assertions', 'runtime', 'input_digest', 'env_snapshot', 'seed'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $payload, 'Champ interne exposé : '.$forbidden);
        }
    }

    public function test_timed_out_derives_inconclusive_though_column_is_null(): void
    {
        [$actor, $comparison] = $this->setupBudgetExceededComparison();
        $this->loginAs($actor);
        $response = $this->browserRequest('GET', '/api/v1/comparisons/'.$comparison->id);

        $response->assertOk();
        $payload = $response->json('data');
        $this->assertSame('timed_out', $payload['state']);
        $this->assertSame('inconclusive', $payload['outcome']);
        // Confirme que la colonne n'a jamais été écrite (CHECK comparison_runs_outcome_requires_terminal).
        $this->assertDatabaseHas('comparison_runs', ['id' => $comparison->id, 'outcome' => null]);
    }

    public function test_other_members_cannot_read_someone_elses_comparison_even_after_reconciliation(): void
    {
        [$owner, $comparison] = $this->setupImprovedComparison();
        $other = User::factory()->verified()->create();
        $this->loginAs($other);
        $response = $this->browserRequest('GET', '/api/v1/comparisons/'.$comparison->id);
        $response->assertNotFound();
    }

    public function test_hidden_capsule_at_the_source_does_not_change_payload_shape(): void
    {
        // Les mutations sur une version publiée sont verrouillées par
        // b22_guard_capsule_version. On n'exerce donc que le masquage de la
        // capsule parente, ce qui suffit à démontrer que la Ressource BV208
        // ne modifie jamais sa forme en fonction d'un retrait au niveau catalogue
        // ni n'y fait fuiter des champs internes (assertions, runtime, digest,
        // env_snapshot, seed sont absents par construction de la projection).
        [$actor, $comparison, $version] = $this->setupImprovedComparisonReturningVersion();
        Capsule::query()->whereKey($version->capsule_id)->update(['visibility' => 'hidden']);
        $this->loginAs($actor);
        $response = $this->browserRequest('GET', '/api/v1/comparisons/'.$comparison->id);
        $response->assertOk();
        $payload = $response->json('data');
        foreach (['assertions', 'runtime', 'input_digest', 'env_snapshot', 'seed'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $payload, 'Champ interne exposé : '.$forbidden);
        }
    }

    public function test_no_mutation_route_touches_comparisons_outcome(): void
    {
        $writers = ['POST', 'PATCH', 'PUT', 'DELETE'];
        $seen = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = (string) $route->uri();
            if (! preg_match('#api/v1/comparisons/[^/]+#', $uri) || str_contains($uri, 'comparison-profiles')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                $method = strtoupper($method);
                $seen[] = $method.' '.$uri;
                $this->assertNotContains(
                    $method,
                    $writers,
                    'Aucune route mutante sur /comparisons/{id} (BV208 lit seulement) : '.$method.' '.$uri,
                );
            }
        }
        $this->assertNotEmpty($seen);
    }

    /** @return array{0: User, 1: ComparisonRun} */
    private function setupImprovedComparison(): array
    {
        [$actor, $comparison] = $this->setupImprovedComparisonReturningVersion();

        return [$actor, $comparison];
    }

    /** @return array{0: User, 1: ComparisonRun, 2: CapsuleVersion} */
    private function setupImprovedComparisonReturningVersion(): array
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $definitionId = $this->definition($version);
        $actor = User::factory()->verified()->create();
        $comparison = $this->pendingComparisonRun($profileId, $actor);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Baseline, $version);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Candidate, $version);
        $this->recordTerminalResult($comparison, ComparisonSide::Baseline, LabRunState::Failed, ['a' => false, 'b' => true]);
        $this->recordTerminalResult($comparison, ComparisonSide::Candidate, LabRunState::Passed, ['a' => true, 'b' => true]);
        app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        return [$actor, $comparison->fresh() ?? $comparison, $version];
    }

    /** @return array{0: User, 1: ComparisonRun} */
    private function setupBudgetExceededComparison(): array
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $definitionId = $this->definition($version);
        $actor = User::factory()->verified()->create();
        $comparison = $this->pendingComparisonRun($profileId, $actor);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Baseline, $version);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Candidate, $version);
        $this->ageComparisonBeyondBudget($comparison);
        app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        return [$actor, $comparison->fresh() ?? $comparison];
    }

    private function loginAs(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}
