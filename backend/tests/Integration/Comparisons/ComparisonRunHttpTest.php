<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use App\Enums\Comparisons\ComparisonSide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\PostgresTestCase;
use Tests\Support\Comparisons\ComparisonRunFixtures;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\SpaHttpRequests;

final class ComparisonRunHttpTest extends PostgresTestCase
{
    use ComparisonRunFixtures, RefreshDatabase, RegistryFixtures, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_anonymous_cannot_read_a_comparison(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('GET', '/api/v1/comparisons/00000000-0000-4000-8000-000000000001');
        $response->assertUnauthorized();
    }

    public function test_actor_reads_their_queued_comparison_without_outcome_or_digests(): void
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $actor = User::factory()->verified()->create();
        $comparison = $this->pendingComparisonRun($profileId, $actor);
        $definitionId = $this->definition($version);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Baseline, $version);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Candidate, $version);
        $this->loginAs($actor);
        $response = $this->browserRequest('GET', '/api/v1/comparisons/'.$comparison->id);
        $response->assertOk();
        $response->assertJsonPath('data.id', $comparison->id);
        $response->assertJsonPath('data.profile_id', $profileId);
        $response->assertJsonPath('data.state', 'queued');
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $payload = $response->json('data');
        $this->assertArrayNotHasKey('outcome', $payload);
        $this->assertArrayNotHasKey('input_digest', $payload);
        $this->assertArrayNotHasKey('env_snapshot', $payload);
        $this->assertArrayNotHasKey('seed', $payload);
        $this->assertSame('/api/v1/comparisons/'.$comparison->id, $payload['follow_url']);
        $this->assertCount(2, $payload['children_follow_urls']);
        $this->assertStringContainsString('/api/v1/lab-runs/', $payload['children_follow_urls'][0]);
    }

    public function test_other_member_cannot_read_someone_elses_comparison(): void
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $owner = User::factory()->verified()->create();
        $other = User::factory()->verified()->create();
        $comparison = $this->pendingComparisonRun($profileId, $owner);
        $this->loginAs($other);
        $response = $this->browserRequest('GET', '/api/v1/comparisons/'.$comparison->id);
        $response->assertNotFound();
    }

    public function test_unknown_comparison_is_not_found(): void
    {
        $actor = User::factory()->verified()->create();
        $this->loginAs($actor);
        $response = $this->browserRequest('GET', '/api/v1/comparisons/00000000-0000-4000-8000-000000000002');
        $response->assertNotFound();
    }

    public function test_show_rejects_any_query_parameter(): void
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $actor = User::factory()->verified()->create();
        $comparison = $this->pendingComparisonRun($profileId, $actor);
        $this->loginAs($actor);
        $response = $this->browserRequest('GET', '/api/v1/comparisons/'.$comparison->id.'?foo=bar');
        $response->assertStatus(422);
    }

    public function test_no_write_route_touches_comparisons_in_bv205(): void
    {
        $writers = ['POST', 'PATCH', 'PUT', 'DELETE'];
        $seen = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = (string) $route->uri();
            if (! preg_match('#api/v1/comparisons/#', $uri) || str_contains($uri, 'comparison-profiles')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                $method = strtoupper($method);
                $seen[] = $method.' '.$uri;
                $this->assertNotContains(
                    $method,
                    $writers,
                    'BV205 reste en lecture seule sur /comparisons : '.$method.' '.$uri,
                );
            }
        }
        $this->assertNotEmpty($seen, 'La route GET /comparisons/{id} doit être enregistrée en BV205.');
    }

    private function loginAs(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}
