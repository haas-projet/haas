<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Models\Capsules\Capsule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\SpaHttpRequests;
use Tests\Support\TestDatabaseGuard;

/**
 * Matrice d'accès : chaque route de backend/routes/api/capsules-lab.php doit
 * imposer son contrat de pile middleware RÉEL (vérifié par route:list).
 * Les routes signées (artifact/stream, artifact/notices) rejettent déjà
 * l'anonyme par auth:sanctum avant signed:relative ; le test anonyme reste
 * donc 401 pour elles.
 */
final class RouteAccessMatrixTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    private const CAPSULE_UUID = '00000000-0000-0000-0000-000000000001';

    private const VERSION_UUID = '00000000-0000-0000-0000-000000000002';

    private const ARTIFACT_UUID = '00000000-0000-0000-0000-000000000003';

    private const CASE_UUID = '00000000-0000-0000-0000-000000000004';

    private const COMPARISON_UUID = '00000000-0000-0000-0000-000000000005';

    private const LAB_RUN_UUID = '00000000-0000-0000-0000-000000000006';

    private const PUBLIC_SLUG = 'exemple-capsule';

    private const PUBLIC_VERSION_LABEL = '1.0.0';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();
    }

    protected function tearDown(): void
    {
        try {
            TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Chaque entrée : [verbe, chemin absolu, nom de route, nature, signed].
     * Nature : 'public' = anonyme autorisé ; 'write' = membre vérifié actif ;
     * 'admin' = modérateur ou admin.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool}>
     */
    public static function matrix(): array
    {
        $capsule = self::CAPSULE_UUID;
        $version = self::VERSION_UUID;
        $artifact = self::ARTIFACT_UUID;
        $case = self::CASE_UUID;
        $comparison = self::COMPARISON_UUID;
        $labRun = self::LAB_RUN_UUID;
        $slug = self::PUBLIC_SLUG;
        $label = self::PUBLIC_VERSION_LABEL;

        return [
            'capsules.catalogue.index' => ['GET', '/api/v1/capsules', 'capsules.catalogue.index', 'public', false],
            'capsules.catalogue.show' => ['GET', "/api/v1/capsules/{$slug}", 'capsules.catalogue.show', 'public', false],
            'capsules.catalogue.show.version' => ['GET', "/api/v1/capsules/{$slug}/versions/{$label}", 'capsules.catalogue.show.version', 'public', false],
            'capsules.drafts.store' => ['POST', '/api/v1/capsules', 'capsules.drafts.store', 'write', false],
            'capsules.versions.drafts.store' => ['POST', "/api/v1/capsules/{$capsule}/versions", 'capsules.versions.drafts.store', 'write', false],
            'capsules.versions.drafts.update' => ['PATCH', "/api/v1/capsules/{$capsule}/versions/{$version}", 'capsules.versions.drafts.update', 'write', false],
            'capsules.versions.submit-review' => ['POST', "/api/v1/capsules/{$capsule}/versions/{$version}/submit-review", 'capsules.versions.submit-review', 'write', false],
            'admin.capsules.versions.request-changes' => ['POST', "/api/v1/admin/capsules/{$capsule}/versions/{$version}/request-changes", 'admin.capsules.versions.request-changes', 'admin', false],
            'admin.capsules.versions.publish' => ['POST', "/api/v1/admin/capsules/{$capsule}/versions/{$version}/publish", 'admin.capsules.versions.publish', 'admin', false],
            'capsules.versions.artifact.issue' => ['GET', "/api/v1/capsules/{$capsule}/versions/{$version}/artifact/{$artifact}", 'capsules.versions.artifact.issue', 'write', false],
            'capsules.versions.artifact.stream' => ['GET', "/api/v1/capsules/{$capsule}/versions/{$version}/artifact/{$artifact}/stream", 'capsules.versions.artifact.stream', 'write', true],
            'capsules.versions.artifact.notices' => ['GET', "/api/v1/capsules/{$capsule}/versions/{$version}/artifact/{$artifact}/notices", 'capsules.versions.artifact.notices', 'write', true],
            'capsules.favorite.put' => ['PUT', "/api/v1/capsules/{$capsule}/favorite", 'capsules.favorite.put', 'write', false],
            'capsules.favorite.delete' => ['DELETE', "/api/v1/capsules/{$capsule}/favorite", 'capsules.favorite.delete', 'write', false],
            'capsules.versions.reuse-reports.store' => ['POST', "/api/v1/capsules/{$capsule}/versions/{$version}/reuse-reports", 'capsules.versions.reuse-reports.store', 'write', false],
            'versions.lab-definition' => ['GET', "/api/v1/versions/{$version}/lab-definition", 'versions.lab-definition', 'public', false],
            'versions.verification-summary' => ['GET', "/api/v1/versions/{$version}/verification-summary", 'versions.verification-summary', 'public', false],
            'versions.comparison-profiles' => ['GET', "/api/v1/versions/{$version}/comparison-profiles", 'versions.comparison-profiles', 'write', false],
            'comparisons.show' => ['GET', "/api/v1/comparisons/{$comparison}", 'comparisons.show', 'write', false],
            'comparisons.store' => ['POST', '/api/v1/comparisons', 'comparisons.store', 'write', false],
            'lab-runs.store' => ['POST', '/api/v1/lab-runs', 'lab-runs.store', 'write', false],
            'lab-runs.show' => ['GET', "/api/v1/lab-runs/{$labRun}", 'lab-runs.show', 'write', false],
            'verification-cases.index' => ['GET', '/api/v1/verification-cases', 'verification-cases.index', 'write', false],
            'verification-cases.store' => ['POST', '/api/v1/verification-cases', 'verification-cases.store', 'write', false],
            'verification-cases.show' => ['GET', "/api/v1/verification-cases/{$case}", 'verification-cases.show', 'write', false],
            'verification-cases.update' => ['PATCH', "/api/v1/verification-cases/{$case}", 'verification-cases.update', 'write', false],
            'verification-cases.submit' => ['POST', "/api/v1/verification-cases/{$case}/submit", 'verification-cases.submit', 'write', false],
            'verification-cases.withdraw' => ['POST', "/api/v1/verification-cases/{$case}/withdraw", 'verification-cases.withdraw', 'write', false],
            'admin.verification-cases.review' => ['POST', "/api/v1/admin/verification-cases/{$case}/review", 'admin.verification-cases.review', 'admin', false],
        ];
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string, 3: string, 4: bool}> */
    public static function authRequiredRoutes(): iterable
    {
        foreach (self::matrix() as $key => $row) {
            if ($row[3] !== 'public') {
                yield $key => $row;
            }
        }
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string, 3: string, 4: bool}> */
    public static function publicRoutes(): iterable
    {
        foreach (self::matrix() as $key => $row) {
            if ($row[3] === 'public') {
                yield $key => $row;
            }
        }
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string, 3: string, 4: bool}> */
    public static function adminRoutes(): iterable
    {
        foreach (self::matrix() as $key => $row) {
            if ($row[3] === 'admin') {
                yield $key => $row;
            }
        }
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string, 3: string, 4: bool}> */
    public static function writeRoutes(): iterable
    {
        foreach (self::matrix() as $key => $row) {
            if ($row[3] === 'write') {
                yield $key => $row;
            }
        }
    }

    public function test_matrix_covers_every_capsules_lab_route(): void
    {
        $expected = array_keys(self::matrix());
        $actual = [];
        $prefixes = [
            'capsules.catalogue.', 'capsules.drafts.', 'capsules.versions.', 'capsules.favorite.',
            'admin.capsules.versions.', 'admin.verification-cases.',
            'versions.lab-definition', 'versions.verification-summary', 'versions.comparison-profiles',
            'comparisons.', 'lab-runs.', 'verification-cases.',
        ];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null) {
                continue;
            }
            foreach ($prefixes as $prefix) {
                if ($prefix === $name || str_starts_with($name, $prefix)) {
                    $actual[] = $name;

                    break;
                }
            }
        }
        $expected = array_unique($expected);
        $actual = array_unique($actual);
        sort($expected);
        sort($actual);
        $missing = array_values(array_diff($actual, $expected));
        $this->assertSame([], $missing, 'Routes du domaine absentes de la matrice : '.implode(', ', $missing));
    }

    #[DataProvider('authRequiredRoutes')]
    public function test_anonymous_receives_401_on_auth_required(string $verb, string $path, string $name, string $kind, bool $signed): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest($verb, $path);
        $this->assertSame(
            401,
            $response->status(),
            "La route anonyme [{$name}] doit répondre 401, elle a répondu {$response->status()}",
        );
    }

    #[DataProvider('publicRoutes')]
    public function test_anonymous_can_access_public_routes(string $verb, string $path, string $name, string $kind, bool $signed): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest($verb, $path);
        $this->assertNotSame(
            401,
            $response->status(),
            "La route publique [{$name}] ne doit pas exiger d'authentification (vu 401)",
        );
        $this->assertNotSame(
            403,
            $response->status(),
            "La route publique [{$name}] ne doit pas être interdite à l'anonyme (vu 403)",
        );
    }

    #[DataProvider('authRequiredRoutes')]
    public function test_unverified_member_refused(string $verb, string $path, string $name, string $kind, bool $signed): void
    {
        $member = User::factory()->unverified()->create();
        $this->loginAs($member);
        $response = $this->browserRequest($verb, $path);
        // Refus par le middleware `verified` = 403 ; refus par `auth:sanctum`
        // (session non ouverte parce que le login a échoué pour un compte non
        // vérifié) = 401. Les deux sont des refus légitimes.
        $this->assertContains(
            $response->status(),
            [401, 403],
            "La route [{$name}] doit refuser un membre non vérifié (401 ou 403), vu {$response->status()}",
        );
    }

    #[DataProvider('authRequiredRoutes')]
    public function test_suspended_member_refused(string $verb, string $path, string $name, string $kind, bool $signed): void
    {
        $member = User::factory()->verified()->suspended()->create();
        $this->loginAs($member);
        $response = $this->browserRequest($verb, $path);
        // Un compte suspendu peut être rejeté à l'ouverture de session (401)
        // ou pendant la requête par EnsureAccountIsActive (403 InactiveAccount).
        $this->assertContains(
            $response->status(),
            [401, 403],
            "La route [{$name}] doit refuser un membre suspendu (401 ou 403), vu {$response->status()}",
        );
    }

    #[DataProvider('writeRoutes')]
    public function test_active_member_passes_auth_on_write(string $verb, string $path, string $name, string $kind, bool $signed): void
    {
        $member = User::factory()->verified()->create();
        $this->loginAs($member);
        $response = $this->browserRequest($verb, $path);
        $this->assertNotSame(
            401,
            $response->status(),
            "La route d'écriture [{$name}] doit laisser passer un membre actif vérifié (vu 401)",
        );
        // 404 ou 422 métier acceptés ; les routes signées rejettent par 403 (hors du périmètre d'accès).
        if (! $signed) {
            $this->assertNotSame(
                403,
                $response->status(),
                "La route d'écriture [{$name}] ne doit pas refuser un membre actif vérifié par auth (vu 403)",
            );
        }
    }

    #[DataProvider('adminRoutes')]
    public function test_simple_member_refused_on_admin(string $verb, string $path, string $name, string $kind, bool $signed): void
    {
        $member = User::factory()->verified()->create();
        $this->loginAs($member);
        $response = $this->browserRequest($verb, $path);
        // Un simple membre ne doit jamais obtenir un succès 2xx sur une route
        // admin. 403 (Policy du contrôleur), 404 (ressource invisible),
        // 422 (corps invalide avant Policy) sont tous des refus acceptables.
        $this->assertNotContains(
            $response->status(),
            [200, 201, 202, 204],
            "La route admin [{$name}] doit refuser un simple membre, vu {$response->status()}",
        );
        $this->assertNotSame(
            401,
            $response->status(),
            "La route admin [{$name}] ne doit pas répondre 401 à un membre connecté",
        );
    }

    #[DataProvider('adminRoutes')]
    public function test_moderator_passes_auth_on_admin(string $verb, string $path, string $name, string $kind, bool $signed): void
    {
        $moderator = User::factory()->verified()->moderator()->create();
        $this->loginAs($moderator);
        $response = $this->browserRequest($verb, $path);
        $this->assertNotSame(
            401,
            $response->status(),
            "La route admin [{$name}] doit laisser passer un modérateur par auth (vu 401)",
        );
    }

    public function test_write_route_returns_401_not_403_to_anonymous(): void
    {
        $capsule = Capsule::factory()->create(['slug' => 'anonyme-matrix']);
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('PUT', "/api/v1/capsules/{$capsule->id}/favorite");
        $this->assertSame(
            401,
            $response->status(),
            "Une route qui exige un utilisateur renvoie 401 à l'anonyme (jamais 403)",
        );
    }

    public function test_catalogue_public_rejects_beyond_sixty_requests_per_minute(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        for ($i = 0; $i < 60; $i++) {
            $this->browserRequest('GET', '/api/v1/capsules');
        }
        $response = $this->browserRequest('GET', '/api/v1/capsules');
        $this->assertSame(429, $response->status(), 'La 61e requête anonyme doit être limitée');
        $this->assertNotNull($response->headers->get('Retry-After'));
        Cache::store('array')->flush();
    }

    public function test_lab_definition_rejects_beyond_sixty_requests_per_minute(): void
    {
        $uuid = self::VERSION_UUID;
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        for ($i = 0; $i < 60; $i++) {
            $this->browserRequest('GET', "/api/v1/versions/{$uuid}/lab-definition");
        }
        $response = $this->browserRequest('GET', "/api/v1/versions/{$uuid}/lab-definition");
        $this->assertSame(429, $response->status(), 'La 61e requête anonyme doit être limitée');
        $this->assertNotNull($response->headers->get('Retry-After'));
        Cache::store('array')->flush();
    }

    public function test_capsule_drafts_group_rejects_beyond_thirty_requests_per_minute(): void
    {
        $member = User::factory()->verified()->create();
        $this->loginAs($member);
        $capsule = self::CAPSULE_UUID;
        $version = self::VERSION_UUID;
        for ($i = 0; $i < 30; $i++) {
            $this->browserRequest('POST', "/api/v1/capsules/{$capsule}/versions/{$version}/submit-review");
        }
        $response = $this->browserRequest('POST', "/api/v1/capsules/{$capsule}/versions/{$version}/submit-review");
        $this->assertSame(429, $response->status(), 'La 31e requête d\'un membre doit être limitée');
        $this->assertNotNull($response->headers->get('Retry-After'));
        Cache::store('array')->flush();
    }

    public function test_admin_capsules_group_rejects_beyond_thirty_requests_per_minute(): void
    {
        $moderator = User::factory()->verified()->moderator()->create();
        $this->loginAs($moderator);
        $capsule = self::CAPSULE_UUID;
        $version = self::VERSION_UUID;
        for ($i = 0; $i < 30; $i++) {
            $this->browserRequest('POST', "/api/v1/admin/capsules/{$capsule}/versions/{$version}/publish");
        }
        $response = $this->browserRequest('POST', "/api/v1/admin/capsules/{$capsule}/versions/{$version}/publish");
        $this->assertSame(429, $response->status(), 'La 31e requête admin doit être limitée');
        $this->assertNotNull($response->headers->get('Retry-After'));
        Cache::store('array')->flush();
    }

    public function test_favorite_and_reuse_group_rejects_beyond_thirty_requests_per_minute(): void
    {
        $member = User::factory()->verified()->create();
        $this->loginAs($member);
        $capsule = Capsule::factory()->create(['slug' => 'cible-throttle']);
        for ($i = 0; $i < 30; $i++) {
            $this->browserRequest('PUT', "/api/v1/capsules/{$capsule->id}/favorite");
        }
        $response = $this->browserRequest('PUT', "/api/v1/capsules/{$capsule->id}/favorite");
        $this->assertSame(429, $response->status(), 'La 31e requête favoris doit être limitée');
        $this->assertNotNull($response->headers->get('Retry-After'));
        Cache::store('array')->flush();
    }

    /**
     * ThrottleRequests::handle (vendor) construit la clé comme
     * `$prefix.resolveRequestSignature($request)` où la signature est l'id
     * utilisateur (authentifié) ou `domaine|ip` : aucun nom ni chemin de route
     * n'y figure. Deux groupes `throttle:max,minutes` sans 3e paramètre
     * partagent donc leur compteur pour un même membre ou une même IP.
     */
    public function test_read_quota_counter_does_not_consume_write_group_budget(): void
    {
        $member = User::factory()->verified()->create();
        $this->loginAs($member);
        $labRun = self::LAB_RUN_UUID;
        for ($i = 0; $i < 30; $i++) {
            $this->browserRequest('GET', "/api/v1/lab-runs/{$labRun}");
        }
        $response = $this->browserRequest('POST', '/api/v1/lab-runs');
        $this->assertNotSame(
            429,
            $response->status(),
            'Les quotas de GET /lab-runs/{id} (60/min) et POST /lab-runs (30/min) doivent être indépendants',
        );
        Cache::store('array')->flush();
    }

    public function test_catalogue_counter_does_not_limit_lab_definition_group(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        for ($i = 0; $i < 61; $i++) {
            $this->browserRequest('GET', '/api/v1/capsules');
        }
        $uuid = self::VERSION_UUID;
        $response = $this->browserRequest('GET', "/api/v1/versions/{$uuid}/lab-definition");
        $this->assertNotSame(
            429,
            $response->status(),
            'Le compteur épuisé du catalogue public ne doit pas limiter GET /versions/{id}/lab-definition',
        );
        Cache::store('array')->flush();
    }

    private function loginAs(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}
