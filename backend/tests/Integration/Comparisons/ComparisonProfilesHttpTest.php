<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\PostgresTestCase;
use Tests\Support\Comparisons\ComparisonFixtures;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\SpaHttpRequests;

final class ComparisonProfilesHttpTest extends PostgresTestCase
{
    use ComparisonFixtures, RefreshDatabase, RegistryFixtures, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_anonymous_cannot_list_comparison_profiles(): void
    {
        $version = $this->publishedVersion();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/comparison-profiles');
        $response->assertUnauthorized();
    }

    public function test_published_version_without_profile_returns_empty_list(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->loginAs($user);
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/comparison-profiles');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function test_enabled_profile_is_returned_without_internal_digests_or_paths(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->enabledComparisonProfile($version);
        $this->loginAs($user);
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/comparison-profiles');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $profile = $response->json('data.0');
        $this->assertSame('b1.idempotent.v1', $profile['family_key']);
        $this->assertSame($version->id, $profile['candidate_version_id']);
        $this->assertSame('1.0.0', $profile['input_schema_version']);
        $this->assertTrue($profile['enabled']);
        $this->assertSame(
            ['nominal_event', 'duplicate_event', 'distinct_events', 'invalid_event'],
            $profile['allowed_scenarios'],
        );
        $this->assertArrayNotHasKey('suite_digest', $profile);
        $this->assertArrayNotHasKey('baseline_manifest', $profile);
        $this->assertArrayNotHasKey('code_digest', $profile);
        $this->assertArrayNotHasKey('created_at', $profile);
    }

    public function test_disabled_profile_is_absent_from_the_listing(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->enabledComparisonProfile($version, ['enabled' => false]);
        $this->loginAs($user);
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/comparison-profiles');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_withdrawn_version_is_treated_as_not_found(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->enabledComparisonProfile($version);
        CapsuleVersion::query()->whereKey($version->id)->update(['state' => CapsuleVersionState::Withdrawn->value]);
        $this->loginAs($user);
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/comparison-profiles');
        $response->assertNotFound();
    }

    public function test_unknown_version_is_treated_as_not_found(): void
    {
        $user = User::factory()->verified()->create();
        $this->loginAs($user);
        $response = $this->browserRequest('GET', '/api/v1/versions/00000000-0000-4000-8000-000000000001/comparison-profiles');
        $response->assertNotFound();
    }

    public function test_hidden_capsule_hides_profiles_of_published_versions(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->enabledComparisonProfile($version);
        Capsule::query()->whereKey($version->capsule_id)->update(['visibility' => CapsuleVisibility::Hidden->value]);
        $this->loginAs($user);
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/comparison-profiles');
        $response->assertNotFound();
    }

    public function test_listing_rejects_any_query_parameter(): void
    {
        $user = User::factory()->verified()->create();
        $version = $this->publishedVersion();
        $this->loginAs($user);
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/comparison-profiles?foo=bar');
        $response->assertStatus(422);
    }

    public function test_no_write_route_touches_comparison_profiles(): void
    {
        $forbidden = ['POST', 'PATCH', 'PUT', 'DELETE'];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_contains((string) $route->uri(), 'comparison-profiles')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                $this->assertNotContains(
                    strtoupper($method),
                    $forbidden,
                    'Aucune route publique ne doit écrire comparison_profiles : '.$method.' '.$route->uri(),
                );
            }
        }
    }

    private function loginAs(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}
