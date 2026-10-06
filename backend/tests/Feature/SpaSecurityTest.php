<?php

namespace Tests\Feature;

use App\Support\Identity\SpaConfiguration;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SpaHttpRequests;
use Tests\TestCase;

final class SpaSecurityTest extends TestCase
{
    use SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa();
    }

    public function test_csrf_initialization_has_correct_cookie_flags_and_cors(): void
    {
        $response = $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com')
            ->assertHeader('Access-Control-Allow-Credentials', 'true')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Request-ID');
        $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());
        foreach (['haas_session', 'XSRF-TOKEN'] as $name) {
            $cookie = $cookies->get($name);
            $this->assertNotNull($cookie);
            $this->assertTrue($cookie->isSecure());
            $this->assertSame('.haas.example.com', $cookie->getDomain());
            $this->assertSame('lax', $cookie->getSameSite());
            $this->assertSame($name === 'haas_session', $cookie->isHttpOnly());
        }
    }

    public function test_local_configuration_issues_host_only_cookies_over_http(): void
    {
        config([
            'app.url' => 'http://localhost:8000',
            'spa.frontend_url' => 'http://localhost:5173',
            'cors.allowed_origins' => ['http://localhost:5173'],
            'sanctum.stateful' => ['localhost:5173', 'localhost:8000'],
            'session.domain' => null, 'session.secure' => false,
        ]);
        $response = $this->call('GET', 'http://localhost:8000/sanctum/csrf-cookie', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173', 'HTTP_ACCEPT' => 'application/json',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
        $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());
        foreach (['haas_session', 'XSRF-TOKEN'] as $name) {
            $cookie = $cookies->get($name);
            $this->assertNotNull($cookie);
            $this->assertFalse($cookie->isSecure());
            $this->assertNull($cookie->getDomain());
            $this->assertSame('lax', $cookie->getSameSite());
            $this->assertSame($name === 'haas_session', $cookie->isHttpOnly());
        }
    }

    #[DataProvider('untrustedOrigins')]
    public function test_untrusted_origins_cannot_open_a_session(string $origin): void
    {
        $response = $this->browserRequest('GET', '/sanctum/csrf-cookie', headers: ['Origin' => $origin])
            ->assertForbidden()->assertCookieMissing('haas_session');
        $this->assertNotSame($origin, $response->headers->get('Access-Control-Allow-Origin'));
    }

    /** @return iterable<string, array{string}> */
    public static function untrustedOrigins(): iterable
    {
        yield 'external' => ['https://untrusted.example.test'];
        yield 'B2' => ['https://demo.example.com'];
        yield 'preview' => ['https://preview.vercel.app'];
        yield 'suffix spoof' => ['https://app.haas.example.com.untrusted.test'];
        yield 'wrong scheme' => ['http://app.haas.example.com'];
        yield 'null origin' => ['null'];
        yield 'wrong port' => ['https://app.haas.example.com:444'];
    }

    public function test_preflight_is_bounded_and_untrusted_preflight_has_no_cors_permission(): void
    {
        $this->browserRequest('OPTIONS', '/login', headers: ['Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'content-type,x-xsrf-token'])
            ->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com')
            ->assertHeader('Access-Control-Allow-Methods', 'GET, HEAD, POST, PATCH, OPTIONS');
        $this->browserRequest('OPTIONS', '/login', headers: ['Origin' => 'https://preview.vercel.app', 'Access-Control-Request-Method' => 'POST'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com');
    }

    #[DataProvider('csrfPaths')]
    public function test_csrf_is_required_even_with_same_origin_hint(string $path): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', $path, headers: ['Sec-Fetch-Site' => 'same-origin'], sendXsrf: false)
            ->assertStatus(419)->assertJsonPath('error.code', 'CSRF_TOKEN_MISMATCH')
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com');
    }

    /** @return iterable<array{string}> */
    public static function csrfPaths(): iterable
    {
        yield ['/login'];
        yield ['/register'];
        yield ['/api/v1/session-fixture'];
    }

    public function test_unknown_fields_bad_input_and_errors_have_cors(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => 'invalid', 'password' => ['invalid'], 'remember' => true])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['email', 'password', 'remember']]])
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com');
        $this->browserRequest('GET', '/api/v1/session-fixture', headers: ['Authorization' => 'Bearer 1|fictitious-token'])
            ->assertUnauthorized()->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com');
    }

    public function test_login_throttle_has_retry_after_and_cors(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->browserRequest('POST', '/login')->assertUnprocessable();
        }
        $this->browserRequest('POST', '/login')->assertStatus(429)->assertHeader('Retry-After')
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com');
    }

    public function test_origin_and_referer_must_both_be_trusted(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie', headers: ['Referer' => 'https://demo.example.com/test'])
            ->assertForbidden()->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com');
    }

    #[DataProvider('invalidConfiguration')]
    public function test_insecure_production_configuration_is_rejected(string $key, mixed $value): void
    {
        $this->app->instance('env', 'production');
        config([$key => $value]);
        $this->expectException(LogicException::class);
        app(SpaConfiguration::class)->validate();
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidConfiguration(): iterable
    {
        yield 'wildcard CORS' => ['cors.allowed_origins', ['*']];
        yield 'wildcard Sanctum' => ['sanctum.stateful', ['*.haas.example.com']];
        yield 'scheme in Sanctum' => ['sanctum.stateful', ['https://app.haas.example.com']];
        yield 'broad cookie' => ['session.domain', '.example.com'];
        yield 'no Secure' => ['session.secure', false];
        yield 'http origin' => ['spa.frontend_url', 'http://app.haas.example.com'];
    }
}
