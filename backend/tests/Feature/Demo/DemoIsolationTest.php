<?php

namespace Tests\Feature\Demo;

use App\Providers\AppServiceProvider;
use App\Support\Demo\DemoRuntimeGuard;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Database\PostgresConnection;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\SanctumServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Tests\DemoTestCase;

final class DemoIsolationTest extends DemoTestCase
{
    public function test_runtime_has_only_demo_database_no_application_key_or_sanctum(): void
    {
        $this->assertSame(['demo'], array_keys(config('database.connections')));
        $this->assertNull(config('app.key'));
        $this->assertSame([], config('app.previous_keys'));
        $this->assertFalse($this->app->providerIsLoaded(SanctumServiceProvider::class));
        $this->assertFalse($this->app->providerIsLoaded(AppServiceProvider::class));
        $this->assertFalse($this->app->providerIsLoaded(SessionServiceProvider::class));
        $this->assertFalse($this->app->providerIsLoaded(EncryptionServiceProvider::class));
        $this->assertFalse($this->app->providerIsLoaded(AuthServiceProvider::class));
        $this->assertSame(realpath(dirname(__DIR__, 3).'/demo'), realpath(base_path()));
    }

    public function test_inherited_haas_cache_storage_and_secrets_do_not_change_the_runtime(): void
    {
        $sentinel = dirname(__DIR__, 2).'/Fixtures/demo-cache-sentinel.php';
        $environment = ['APP_KEY' => 'sentinelle-fictive', 'DB_PASSWORD' => 'sentinelle-fictive',
            'APP_BASE_PATH' => dirname(__DIR__, 3), 'LARAVEL_STORAGE_PATH' => dirname(__DIR__, 3).'/storage'];
        foreach (['APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_PACKAGES_CACHE', 'APP_SERVICES_CACHE', 'APP_EVENTS_CACHE'] as $name) {
            $environment[$name] = $sentinel;
        }
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/Fixtures/inspect-demo-runtime.php'], dirname(__DIR__, 3), $environment);
        $process->mustRun();
        $metadata = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($metadata['key_absent']);
        $this->assertTrue($metadata['db_password_independent']);
        $this->assertSame(['demo'], $metadata['database_connections']);
        $this->assertEqualsCanonicalizing(['api/v1/b2/health', 'api/v1/b2/demo-orders'], $metadata['routes']);
        foreach (['config_cache', 'routes_cache', 'packages_cache', 'services_cache', 'events_cache', 'storage'] as $name) {
            $this->assertStringStartsWith(base_path().DIRECTORY_SEPARATOR, $metadata[$name]);
        }
    }

    /** @param array<string,string> $headers */
    #[DataProvider('rejectedHeaders')]
    public function test_untrusted_origin_cookie_and_authorization_are_refused_without_database(array $headers): void
    {
        config(['database.connections.demo.port' => 1]);
        $response = $this->postJson('/api/v1/b2/demo-orders', $this->body(), $headers)->assertForbidden();
        $this->assertSame([], $response->headers->getCookies());
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    /** @return iterable<string, array{array<string,string>}> */
    public static function rejectedHeaders(): iterable
    {
        yield 'origin HAAS' => [['Origin' => 'https://app.haas.example.com']];
        yield 'origin inconnu' => [['Origin' => 'https://evil.example.net']];
        yield 'origin null' => [['Origin' => 'null']];
        yield 'cookie HAAS' => [['Origin' => 'https://demo.example.com', 'Cookie' => 'haas_session=fictif; XSRF-TOKEN=fictif']];
        yield 'bearer' => [['Authorization' => 'Bearer fictif']];
        yield 'referer HAAS' => [['Origin' => 'https://demo.example.com', 'Referer' => 'https://app.haas.example.com/']];
        yield 'referer avec credentials' => [['Referer' => 'https://fictif:fictif@demo.example.com/form']];
    }

    public function test_approved_origin_receives_precise_cors_without_credentials_on_errors_and_preflight(): void
    {
        config(['database.connections.demo.port' => 1]);
        $headers = ['Origin' => 'https://demo.example.com'];
        $response = $this->postJson('/api/v1/b2/demo-orders', $this->body(), $headers)->assertUnprocessable();
        $response->assertHeader('Access-Control-Allow-Origin', 'https://demo.example.com')->assertHeaderMissing('Access-Control-Allow-Credentials');
        $this->assertSame([], $response->headers->getCookies());
        $this->call('OPTIONS', '/api/v1/b2/demo-orders', server: [
            'HTTP_ORIGIN' => 'https://demo.example.com', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,idempotency-key',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'https://demo.example.com')->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_haas_host_and_haas_routes_are_unavailable(): void
    {
        $this->getJson('https://api.haas.example.com/api/v1/b2/health')->assertForbidden();
        foreach (['/login', '/sanctum/csrf-cookie', '/api/v1/me', '/api/v1/requests'] as $path) {
            $response = $this->getJson('https://demo-api.example.com'.$path)->assertNotFound();
            $this->assertSame([], $response->headers->getCookies());
        }
    }

    public function test_cookie_domain_misconfiguration_is_refused_closed(): void
    {
        config(['demo.frontend_origin' => 'https://demo.haas.example.com']);
        $this->getJson('/api/v1/b2/health')->assertServiceUnavailable();
    }

    /** @param array<string,mixed> $configuration */
    #[DataProvider('unsafeConfiguration')]
    public function test_invalid_origin_parent_and_non_shared_production_cache_fail_closed(array $configuration): void
    {
        config($configuration + ['database.connections.demo.port' => 1]);
        $this->getJson('/api/v1/b2/health')->assertServiceUnavailable();
    }

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function unsafeConfiguration(): iterable
    {
        yield 'parent vide' => [['demo.haas_cookie_parent' => '']];
        yield 'parent invalide' => [['demo.haas_cookie_parent' => 'haas.example.com/']];
        yield 'parent majuscule' => [['demo.haas_cookie_parent' => '.HAAS.EXAMPLE.COM', 'demo.frontend_origin' => 'https://DEMO.HAAS.EXAMPLE.COM']];
        yield 'API majuscule HAAS' => [['app.url' => 'https://API.HAAS.EXAMPLE.COM']];
        yield 'DNS point final' => [['demo.frontend_origin' => 'https://demo.haas.example.com.']];
        yield 'URL avec credentials' => [['demo.frontend_origin' => 'https://user:secret@demo.example.com']];
        yield 'URL avec chemin' => [['app.url' => 'https://demo-api.example.com/path']];
        yield 'cache array production' => [['app.env' => 'production', 'cache.default' => 'array']];
        yield 'cache file production' => [['app.env' => 'production', 'cache.default' => 'file']];
        yield 'cache connecté HAAS' => [['cache.stores.database.connection' => 'pgsql']];
    }

    public function test_distinct_uppercase_dns_are_normalized_before_cors(): void
    {
        config(['demo.frontend_origin' => 'HTTPS://DEMO.EXAMPLE.COM', 'app.url' => 'HTTPS://DEMO-API.EXAMPLE.COM', 'demo.haas_cookie_parent' => '.HAAS.EXAMPLE.COM']);
        $this->getJson('https://demo-api.example.com/api/v1/b2/health', ['Origin' => 'https://demo.example.com'])
            ->assertOk()->assertHeader('Access-Control-Allow-Origin', 'https://demo.example.com');
    }

    /** @param array<string,mixed> $configuration */
    #[DataProvider('unsafeDatabaseConfiguration')]
    public function test_haas_and_unspecified_database_configurations_are_refused_before_connection(array $configuration): void
    {
        config($configuration + ['database.connections.demo.port' => 1]);
        try {
            app(DemoRuntimeGuard::class)->database();
            $this->fail('Configuration HAAS interdite avant connexion.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }
    }

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function unsafeDatabaseConfiguration(): iterable
    {
        yield 'base HAAS' => [['database.connections.demo.database' => 'haas_app']];
        yield 'rôle HAAS' => [['database.connections.demo.username' => 'haas_test']];
        yield 'superuser' => [['database.connections.demo.username' => 'postgres']];
        yield 'référence HAAS absente' => [['demo.haas_database' => null]];
        yield 'URL supplémentaire' => [['database.connections.demo.url' => 'pgsql://haas']];
        yield 'lecture autre connexion' => [['database.connections.demo.read' => ['database' => 'haas_app']]];
    }

    #[DataProvider('privilegedRoles')]
    public function test_real_role_flags_and_non_connect_proof_must_all_be_safe(string $flag, mixed $value): void
    {
        $row = (object) ['database' => config('database.connections.demo.database'), 'username' => config('database.connections.demo.username'),
            'isolation' => 'read committed', 'rolsuper' => false, 'rolcreatedb' => false, 'rolcreaterole' => false,
            'rolreplication' => false, 'rolbypassrls' => false, 'membership' => false, 'haas_connect' => false];
        $row->$flag = $value;
        $connection = \Mockery::mock(PostgresConnection::class);
        $connection->shouldReceive('selectOne')->once()->andReturn($row);
        DB::shouldReceive('connection')->with('demo')->andReturn($connection);
        try {
            app(DemoRuntimeGuard::class)->database();
            $this->fail('Privilèges réels ou isolation transactionnelle interdits.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }
    }

    /** @return iterable<string,array{string,mixed}> */
    public static function privilegedRoles(): iterable
    {
        foreach (['rolsuper', 'rolcreatedb', 'rolcreaterole', 'rolreplication', 'rolbypassrls', 'membership', 'haas_connect'] as $flag) {
            yield $flag => [$flag, true];
        }
        yield 'base HAAS inexistante' => ['haas_connect', null];
        yield 'snapshot ancien' => ['isolation', 'repeatable read'];
    }

    public function test_large_bodies_and_non_json_are_refused_without_database(): void
    {
        config(['database.connections.demo.port' => 1]);
        $this->postJson('/api/v1/b2/demo-orders', ['order_ref' => str_repeat('a', 2049)], ['Origin' => 'https://demo.example.com'])
            ->assertStatus(413)->assertHeader('Access-Control-Allow-Origin', 'https://demo.example.com')->assertHeaderMissing('Access-Control-Allow-Credentials');
        $this->post('/api/v1/b2/demo-orders', $this->body(), ['Origin' => 'https://demo.example.com'])
            ->assertStatus(415)->assertHeader('Access-Control-Allow-Origin', 'https://demo.example.com')->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_b2_routes_are_documented_in_separate_contract(): void
    {
        $paths = Yaml::parseFile(dirname(__DIR__, 4).'/docs/api/DEMO_OPENAPI.yaml')['paths'];
        $expected = [];
        foreach ($paths as $path => $item) {
            $expected[] = (isset($item['get']) ? 'GET' : 'POST').' '.$path;
        }
        $actual = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $actual[] = $method.' /'.$route->uri();
            }
        }
        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    /** @return array<string,int|string> */
    private function body(): array
    {
        return ['order_ref' => 'demo-0001', 'amount_minor' => 1299, 'currency' => 'EUR'];
    }
}
