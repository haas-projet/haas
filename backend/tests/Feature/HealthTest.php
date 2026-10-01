<?php

namespace Tests\Feature;

use Tests\TestCase;

final class HealthTest extends TestCase
{
    public function test_health_is_minimal_and_does_not_need_database_or_session(): void
    {
        config(['session.driver' => 'database', 'database.connections.pgsql.port' => 1]);

        $response = $this->get('/up');

        $response->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_unknown_api_route_returns_json_without_debug_details(): void
    {
        $this->get('/api/v1/unknown')->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonMissingPath('trace')->assertJsonMissingPath('exception');
    }
}
