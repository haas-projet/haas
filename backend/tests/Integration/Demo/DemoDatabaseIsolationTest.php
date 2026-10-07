<?php

namespace Tests\Integration\Demo;

use App\Data\Demo\DemoOrderData;
use App\Exceptions\Demo\DemoCapacityReached;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Services\Demo\RecordDemoOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Tests\DemoPostgresTestCase;
use Tests\Support\RefreshDemoDatabase;

final class DemoDatabaseIsolationTest extends DemoPostgresTestCase
{
    use RefreshDemoDatabase;

    public function test_demo_role_cannot_connect_to_application_database_and_is_not_privileged(): void
    {
        $appDatabase = getenv('DB_DATABASE');
        $this->assertIsString($appDatabase);
        $this->assertMatchesRegularExpression('/^haas_[a-z0-9_]+_test$/D', $appDatabase);
        $row = DB::selectOne('SELECT rolsuper, rolcreatedb, rolcreaterole, rolreplication FROM pg_roles WHERE rolname = current_user');
        $this->assertNotNull($row);
        foreach (['rolsuper', 'rolcreatedb', 'rolcreaterole', 'rolreplication'] as $field) {
            $this->assertFalse($row->$field);
        }
        $permission = DB::selectOne('SELECT has_database_privilege(current_user, ?, \'CONNECT\') AS allowed', [$appDatabase]);
        $this->assertNotNull($permission);
        $this->assertFalse($permission->allowed);
        $configuration = config('database.connections.demo');
        try {
            new PDO('pgsql:host='.$configuration['host'].';port='.$configuration['port'].';dbname='.$appDatabase, $configuration['username'], $configuration['password'], [PDO::ATTR_TIMEOUT => 3]);
            $this->fail('Le rôle fictif ne doit pas se connecter à HAAS.');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('permission denied for database', $exception->getMessage());
        }
        $this->assertSame(0, DB::table('demo_orders')->count());
        $this->assertFalse(Schema::hasTable('users'));
    }

    public function test_key_ttl_is_enforced_without_waiting_for_the_scheduler(): void
    {
        $this->freezeTime();
        $service = app(RecordDemoOrderService::class);
        $key = (string) Str::uuid();
        $data = new DemoOrderData('demo-0001', 1299, 'EUR');
        $original = $service->handle($data, $key);
        $this->travel(23)->hours();
        $this->assertTrue($service->handle($data, $key)->replay);
        try {
            $service->handle(new DemoOrderData('demo-0001', 1300, 'EUR'), $key);
            $this->fail('Conflit attendu avant expiration.');
        } catch (IdempotencyConflict) {
            $this->assertDatabaseCount('demo_orders', 1);
        }
        $this->travel(1)->hours();
        $new = $service->handle(new DemoOrderData('demo-0001', 1300, 'EUR'), $key);
        $this->assertFalse($new->replay);
        $this->assertNotSame($original->order->id, $new->order->id);
        $this->assertDatabaseCount('demo_orders', 1);
    }

    public function test_storage_is_bounded_and_replays_remain_available_at_capacity(): void
    {
        $this->freezeTime();
        $service = app(RecordDemoOrderService::class);
        $key = (string) Str::uuid();
        $data = new DemoOrderData('demo-0001', 1299, 'EUR');
        $service->handle($data, $key);
        $rows = [];
        for ($index = 1; $index < 5000; $index++) {
            $rows[] = ['id' => (string) Str::uuid(), 'idempotency_key_hash' => hash('sha256', 'capacity-'.$index), 'payload_hash' => str_repeat('a', 64),
                'order_ref' => 'demo-0002', 'amount_minor' => 1, 'currency' => 'EUR', 'state' => 'confirmed',
                'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addDay()];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('demo_orders')->insert($chunk);
        }
        $this->assertTrue($service->handle($data, $key)->replay);
        try {
            $service->handle($data, (string) Str::uuid());
            $this->fail('Capacité B2 attendue.');
        } catch (DemoCapacityReached) {
            $this->assertDatabaseCount('demo_orders', 5000);
        }
    }

    public function test_http_has_thirty_request_limit_without_cookie_and_no_additional_order(): void
    {
        config(['cache.default' => 'database']);
        $key = (string) Str::uuid();
        $headers = ['Idempotency-Key' => $key, 'Origin' => 'https://demo.example.com'];
        $body = ['order_ref' => 'demo-0001', 'amount_minor' => 1299, 'currency' => 'EUR'];
        $this->postJson('/api/v1/b2/demo-orders', $body, $headers)->assertCreated();
        for ($index = 1; $index < 30; $index++) {
            $this->postJson('/api/v1/b2/demo-orders', $body, $headers)->assertOk();
        }
        $response = $this->postJson('/api/v1/b2/demo-orders', $body, $headers)->assertStatus(429)->assertHeader('Retry-After');
        $response->assertHeader('Access-Control-Allow-Origin', 'https://demo.example.com')->assertHeaderMissing('Access-Control-Allow-Credentials');
        $this->assertSame([], $response->headers->getCookies());
        $this->assertDatabaseCount('demo_orders', 1);
        $this->assertGreaterThan(0, DB::table('cache')->count());
    }
}
