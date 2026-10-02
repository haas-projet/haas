<?php

namespace Tests\Feature;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;
use Throwable;

final class ApiErrorsTest extends TestCase
{
    /** @param Closure(): Throwable $exception */
    #[DataProvider('errors')]
    public function test_errors_have_a_safe_common_contract(Closure $exception, int $status, string $code): void
    {
        config(['app.debug' => true, 'logging.default' => 'null']);
        Route::get('/api/v1/test-error', static fn () => throw $exception());

        $response = $this->get('/api/v1/test-error', ['Accept' => 'text/html']);
        $response->assertStatus($status)->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', $code)
            ->assertExactJsonStructure(['error' => ['code', 'message', 'fields'], 'request_id']);
        $id = $response->json('request_id');
        $this->assertIsString($id);
        $this->assertTrue(Str::isUuid($id));
        $response->assertHeader('X-Request-ID', $id);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('SELECT', $body);
        $this->assertStringNotContainsString('/srv/private', $body);
        $this->assertStringNotContainsString('exception', $body);
        $this->assertStringNotContainsString('trace', $body);
        if ($status === 422) {
            $response->assertJsonPath('error.fields.title.0', 'Le titre est obligatoire.');
        } else {
            $this->assertInstanceOf(\stdClass::class, json_decode($body)->error->fields);
        }
    }

    /** @return iterable<string, array{Closure(): Throwable, int, string}> */
    public static function errors(): iterable
    {
        $private = 'SELECT private_value FROM accounts in /srv/private/config.php';
        yield 'auth' => [fn () => new AuthenticationException($private), 401, 'AUTHENTICATION_REQUIRED'];
        yield 'policy' => [fn () => new AuthorizationException($private), 403, 'ACTION_FORBIDDEN'];
        yield 'hidden' => [fn () => (new AuthorizationException($private))->asNotFound(), 404, 'RESOURCE_NOT_FOUND'];
        yield 'model' => [fn () => (new ModelNotFoundException)->setModel(User::class, ['private-id']), 404, 'RESOURCE_NOT_FOUND'];
        yield 'conflict' => [fn () => new ConflictHttpException($private), 409, 'RESOURCE_CONFLICT'];
        yield 'csrf' => [fn () => new TokenMismatchException($private), 419, 'CSRF_TOKEN_MISMATCH'];
        yield 'validation' => [fn () => ValidationException::withMessages(['title' => ['Le titre est obligatoire.']]), 422, 'VALIDATION_FAILED'];
        yield 'quota' => [fn () => new TooManyRequestsHttpException(60, $private), 429, 'RATE_LIMIT_EXCEEDED'];
        yield 'failure' => [fn () => new RuntimeException($private), 500, 'INTERNAL_ERROR'];
        yield 'unavailable' => [fn () => new ServiceUnavailableHttpException(120, $private), 503, 'SERVICE_UNAVAILABLE'];
    }

    public function test_validation_without_accept_json_still_returns_fields(): void
    {
        Route::post('/api/v1/test-validation', function (Request $request): void {
            $request->validate(['title' => ['required']], ['title.required' => 'Le titre est obligatoire.']);
        });

        $this->post('/api/v1/test-validation')->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.fields.title.0', 'Le titre est obligatoire.');
    }

    public function test_unknown_route_and_wrong_method_are_json(): void
    {
        $this->get('/api/v1/absent', ['Accept' => 'text/html'])->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        $this->get('/api')->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        Route::post('/api/v1/test-method', fn () => ['ok' => true]);
        $this->get('/api/v1/test-method')->assertStatus(405)
            ->assertHeader('Allow', 'POST')->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
    }

    public function test_retry_after_is_preserved_for_throttling_and_unavailability(): void
    {
        Route::get('/api/v1/test-quota', fn () => ['ok' => true])->middleware('throttle:1,1');
        $this->get('/api/v1/test-quota')->assertOk();
        $limited = $this->get('/api/v1/test-quota')->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
        $this->assertGreaterThan(0, (int) $limited->headers->get('Retry-After'));

        Route::get('/api/v1/test-unavailable', fn () => throw new ServiceUnavailableHttpException(120));
        $this->get('/api/v1/test-unavailable')->assertStatus(503)->assertHeader('Retry-After', '120');
    }

    public function test_request_id_is_generated_by_server_and_not_reused(): void
    {
        $clientId = (string) Str::uuid();
        $first = $this->get('/up', ['X-Request-ID' => $clientId])->assertOk();
        $second = $this->get('/api/v1/absent', ['X-Request-ID' => $clientId])->assertNotFound();
        $firstId = $first->headers->get('X-Request-ID');
        $this->assertIsString($firstId);
        $this->assertTrue(Str::isUuid($firstId));
        $this->assertNotSame($clientId, $firstId);
        $this->assertNotSame($clientId, $second->json('request_id'));
        $this->assertNotSame($firstId, $second->json('request_id'));
    }

    public function test_unexpected_failure_is_reported_with_its_request_id(): void
    {
        $exception = new RuntimeException('Erreur de fixture.');
        $logged = [];
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->withArgs(function (string $message, array $context) use (&$logged, $exception): bool {
            $logged = $context;

            return $context['exception'] === $exception;
        });
        $this->app->instance(LoggerInterface::class, $logger);
        Route::get('/api/v1/test-report', fn () => throw $exception);

        $response = $this->get('/api/v1/test-report')->assertStatus(500);
        $this->assertSame($response->json('request_id'), $logged['request_id']);
    }

    public function test_json_negotiation_applies_outside_api_and_html_is_preserved_elsewhere(): void
    {
        $this->getJson('/missing-auth-route')->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        $this->get('/missing-page', ['Accept' => 'text/html'])->assertNotFound()->assertHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
