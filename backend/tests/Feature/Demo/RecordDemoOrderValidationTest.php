<?php

namespace Tests\Feature\Demo;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Validations HTTP de la brique B2 (lot B38).
 *
 * Les tests qui n'exigent pas de base de données vivent ici : toutes les
 * erreurs 422 doivent être levées avant toute écriture. La table
 * `demo_orders` est tenue hors de portée en forçant la connexion vers un
 * port invalide — comme le test de santé — pour s'assurer qu'aucune
 * connexion ne s'ouvre tant qu'un INSERT serait tenté.
 *
 * Les scénarios 201/200/409 vivent dans Integration/Demo pour utiliser
 * PostgreSQL réel.
 */
final class RecordDemoOrderValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Si une connexion est tentée malgré nous, le test échoue bruyamment.
        config(['database.connections.pgsql.port' => 1]);
    }

    public function test_missing_idempotency_key_header_is_rejected_before_any_database_access(): void
    {
        $response = $this->postJson('/api/v1/b2/demo-orders', $this->validBody())->assertUnprocessable();

        $response->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['idempotency_key']]]);
        $this->assertNoSetCookie($response);
    }

    #[DataProvider('invalidIdempotencyKeys')]
    public function test_invalid_idempotency_key_header_is_rejected(string $header): void
    {
        $response = $this->postJson('/api/v1/b2/demo-orders', $this->validBody(), ['Idempotency-Key' => $header])
            ->assertUnprocessable();

        $response->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['idempotency_key']]]);
        $this->assertNoSetCookie($response);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidIdempotencyKeys(): iterable
    {
        yield 'not uuid' => ['not-a-uuid'];
        yield 'uuid v1 instead of v4' => ['f81d4fae-7dec-11d0-a765-00a0c91e6bf6'];
        yield 'two keys concatenated' => ['11111111-1111-4111-8111-111111111111 22222222-2222-4222-8222-222222222222'];
        yield 'leading space' => [' 11111111-1111-4111-8111-111111111111'];
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_body_is_rejected_before_any_database_access(string $field, mixed $value): void
    {
        $payload = $this->validBody();
        $payload[$field] = $value;

        $response = $this->postJson('/api/v1/b2/demo-orders', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable();

        $response->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => [$field]]]);
        $this->assertNoSetCookie($response);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidBodies(): iterable
    {
        yield 'empty order_ref' => ['order_ref', ''];
        yield 'whitespace-only order_ref' => ['order_ref', '   '];
        yield 'order_ref too long' => ['order_ref', str_repeat('a', 65)];
        yield 'amount zero' => ['amount_minor', 0];
        yield 'amount negative' => ['amount_minor', -1];
        yield 'amount string' => ['amount_minor', '12.34'];
        yield 'currency lowercase' => ['currency', 'eur'];
        yield 'currency too short' => ['currency', 'EU'];
        yield 'currency four chars' => ['currency', 'EURO'];
    }

    public function test_unknown_body_field_is_refused(): void
    {
        $payload = $this->validBody();
        $payload['user_id'] = (string) Str::uuid();

        $response = $this->postJson('/api/v1/b2/demo-orders', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable();

        $response->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['user_id']]]);
    }

    public function test_health_route_still_responds_without_session_or_database(): void
    {
        // Ancrage : la configuration de test force une connexion inatteignable.
        // Si le groupe B2 avait réveillé une session, cet appel bronche.
        $this->get('/up')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    /** @return array<string, int|string> */
    private function validBody(): array
    {
        return [
            'order_ref' => 'demo-0001',
            'amount_minor' => 1_299,
            'currency' => 'EUR',
        ];
    }

    /** @param TestResponse<Response> $response */
    private function assertNoSetCookie(TestResponse $response): void
    {
        $this->assertSame([], $response->headers->getCookies(), 'B2 ne doit émettre aucun cookie, même pour une 422.');
    }
}
