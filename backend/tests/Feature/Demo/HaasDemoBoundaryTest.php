<?php

namespace Tests\Feature\Demo;

use Tests\TestCase;

final class HaasDemoBoundaryTest extends TestCase
{
    public function test_haas_runtime_has_no_demo_route_or_database(): void
    {
        $this->assertArrayNotHasKey('demo', config('database.connections'));
        $this->postJson('/api/v1/b2/demo-orders', [])->assertNotFound();
        $this->getJson('/api/v1/b2/health')->assertNotFound();
    }
}
