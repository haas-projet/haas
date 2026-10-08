<?php

namespace Tests\Integration\Demo;

use App\Models\Demo\DemoOrder;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\DemoPostgresTestCase;
use Tests\Support\RefreshDemoDatabase;

/**
 * Parcours HTTP de la brique B2 (lot B38) : création nominale (201),
 * rejeu identique (200 + replay=true), conflit (409), et absence de
 * cookie HAAS/session dans les réponses.
 */
final class RecordDemoOrderHttpTest extends DemoPostgresTestCase
{
    use RefreshDemoDatabase;

    public function test_post_demo_order_creates_a_fictional_order_with_201(): void
    {
        $key = (string) Str::uuid();

        $response = $this->postJson('/api/v1/b2/demo-orders', $this->validBody(), ['Idempotency-Key' => $key])
            ->assertCreated();

        $response->assertJsonPath('data.order_ref', 'demo-0001')
            ->assertJsonPath('data.amount_minor', 1_299)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.state', 'confirmed')
            ->assertHeader('X-Idempotent-Replay', 'false');
        $this->assertNoHaasCookieEmitted($response);
        $this->assertDatabaseCount('demo_orders', 1);
    }

    public function test_same_key_and_same_body_returns_the_original_order_with_200_and_replay_header(): void
    {
        $key = (string) Str::uuid();
        $body = $this->validBody();

        $first = $this->postJson('/api/v1/b2/demo-orders', $body, ['Idempotency-Key' => $key])->assertCreated();
        $firstId = $first->json('data.id');
        $this->assertIsString($firstId);

        $second = $this->postJson('/api/v1/b2/demo-orders', $body, ['Idempotency-Key' => $key])->assertOk();

        $second->assertJsonPath('data.id', $firstId)
            ->assertHeader('X-Idempotent-Replay', 'true');
        $this->assertNoHaasCookieEmitted($second);
        $this->assertDatabaseCount('demo_orders', 1);
    }

    public function test_same_key_but_divergent_body_is_rejected_409_without_overwriting(): void
    {
        $key = (string) Str::uuid();

        $this->postJson('/api/v1/b2/demo-orders', $this->validBody(), ['Idempotency-Key' => $key])->assertCreated();

        $divergent = $this->validBody();
        $divergent['amount_minor'] = 9_999;

        $response = $this->postJson('/api/v1/b2/demo-orders', $divergent, ['Idempotency-Key' => $key])->assertStatus(409);

        $response->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertNoHaasCookieEmitted($response);
        $this->assertDatabaseCount('demo_orders', 1);
        $stored = DemoOrder::query()->firstOrFail();
        $this->assertSame(1_299, $stored->amount_minor, 'La commande d’origine ne doit pas être écrasée.');
    }

    public function test_missing_idempotency_key_header_returns_422_without_writing(): void
    {
        $response = $this->postJson('/api/v1/b2/demo-orders', $this->validBody())->assertUnprocessable();

        $response->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['idempotency_key']]]);
        $this->assertNoHaasCookieEmitted($response);
        $this->assertDatabaseCount('demo_orders', 0);
    }

    public function test_response_never_opens_a_haas_session_or_emits_a_cookie(): void
    {
        $key = (string) Str::uuid();

        $response = $this->postJson('/api/v1/b2/demo-orders', $this->validBody(), ['Idempotency-Key' => $key])
            ->assertCreated();

        $this->assertSame([], $response->headers->getCookies(), 'B2 ne doit émettre aucun cookie.');
        $this->assertFalse($this->app->bound('session'), 'Le runtime B2 ne doit pas disposer du service session.');
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
    private function assertNoHaasCookieEmitted(TestResponse $response): void
    {
        $cookies = $response->headers->getCookies();
        foreach ($cookies as $cookie) {
            $this->fail('Cookie émis par B2 : '.$cookie->getName().'. Isolation du périmètre .haas.example.com rompue.');
        }
        $this->assertNull($response->headers->get('Set-Cookie'), 'Aucun Set-Cookie ne doit être présent.');
    }
}
