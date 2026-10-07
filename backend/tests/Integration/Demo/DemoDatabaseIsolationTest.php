<?php

namespace Tests\Integration\Demo;

use App\Data\Demo\DemoOrderData;
use App\Exceptions\Demo\DemoCapacityReached;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Services\Demo\PurgeExpiredDemoOrdersService;
use App\Services\Demo\RecordDemoOrderService;
use App\Support\Demo\DemoRuntimeGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\DemoPostgresTestCase;
use Tests\Support\RefreshDemoDatabase;

final class DemoDatabaseIsolationTest extends DemoPostgresTestCase
{
    use RefreshDemoDatabase;

    public function test_demo_role_cannot_connect_to_application_database_and_is_not_privileged(): void
    {
        $appDatabase = config('demo.haas_database');
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
        app(DemoRuntimeGuard::class)->database();
    }

    public function test_runtime_refuses_missing_haas_reference_and_a_database_the_demo_role_can_access(): void
    {
        foreach (['haas_missing_b38_test', config('database.connections.demo.database')] as $database) {
            config(['demo.haas_database' => $database]);
            try {
                app(DemoRuntimeGuard::class)->database();
                $this->fail('La garde doit refuser une isolation non prouvée.');
            } catch (HttpException $exception) {
                $this->assertSame(503, $exception->getStatusCode());
                $this->assertDatabaseCount('demo_orders', 0);
            }
        }
    }

    public function test_http_database_guard_precedes_throttle_cache_writes(): void
    {
        config(['cache.default' => 'database', 'demo.haas_database' => 'haas_missing_b38_test']);
        foreach (['/api/v1/b2/demo-orders', '/api/v1/b2/demo-orders/'] as $path) {
            $this->postJson($path, ['order_ref' => 'demo-0001', 'amount_minor' => 1, 'currency' => 'EUR'],
                ['Idempotency-Key' => (string) Str::uuid()])->assertServiceUnavailable();
            $this->assertDatabaseCount('cache', 0);
            $this->assertDatabaseCount('demo_orders', 0);
        }
    }

    public function test_real_cli_database_guard_precedes_prune_and_cache_writes(): void
    {
        $process = new Process([PHP_BINARY, 'demo/artisan', 'demo:prune', '--no-interaction'], dirname(__DIR__, 3),
            ['DEMO_HAAS_DATABASE' => 'haas_missing_b38_test', 'DEMO_ENV' => 'testing', 'DEMO_CACHE_STORE' => 'database'], timeout: 30);
        $this->assertSame(1, $process->run());
        $this->assertStringContainsString('Configuration B2 isolée indisponible.', $process->getOutput().$process->getErrorOutput());
        $this->assertDatabaseCount('cache', 0);
        $this->assertDatabaseCount('cache_locks', 0);
        $this->assertDatabaseCount('demo_orders', 0);
    }

    public function test_purge_removes_expired_cache_and_locks_but_keeps_live_entries(): void
    {
        DB::table('cache')->insert(['key' => 'expired', 'value' => 'i:1;', 'expiration' => time() + 60]);
        DB::table('cache')->insert(['key' => 'live', 'value' => 'i:1;', 'expiration' => time() + 60]);
        DB::table('cache_locks')->insert(['key' => 'expired-lock', 'owner' => 'fictif', 'expiration' => time() + 60]);
        DB::table('cache_locks')->insert(['key' => 'live-lock', 'owner' => 'fictif', 'expiration' => time() + 60]);
        DB::table('cache')->where('key', 'expired')->update(['expiration' => time() - 1]);
        DB::table('cache_locks')->where('key', 'expired-lock')->update(['expiration' => time() - 1]);
        $this->assertSame(0, app(PurgeExpiredDemoOrdersService::class)->purge(new \DateTimeImmutable('-24 hours')));
        $this->assertSame(['live'], DB::table('cache')->pluck('key')->all());
        $this->assertSame(['live-lock'], DB::table('cache_locks')->pluck('key')->all());
    }

    public function test_cache_capacity_preserves_existing_values_and_returns_controlled_http_saturation(): void
    {
        config(['cache.default' => 'database']);
        for ($index = 0; $index < 1000; $index++) {
            Cache::put('capacity-'.$index, 1, 60);
        }
        $this->assertTrue(Cache::put('capacity-0', 2, 60));
        $this->assertSame(2, Cache::get('capacity-0'));
        DB::statement('SAVEPOINT b38_http_cache_capacity');
        $this->postJson('/api/v1/b2/demo-orders', ['order_ref' => 'demo-0001', 'amount_minor' => 1, 'currency' => 'EUR'],
            ['Origin' => 'https://demo.example.com', 'Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(429)->assertHeader('Access-Control-Allow-Origin', 'https://demo.example.com');
        DB::statement('ROLLBACK TO SAVEPOINT b38_http_cache_capacity');
        $this->assertDatabaseCount('cache', 1000);
        $this->assertDatabaseCount('demo_orders', 0);
    }

    public function test_lock_capacity_returns_not_acquired_and_existing_lock_can_be_renewed(): void
    {
        config(['cache.default' => 'database']);
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/Fixtures/record-demo-order-concurrently.php'], dirname(__DIR__, 3),
            ['DEMO_TEST_MODE' => 'cache_locks', 'DEMO_CACHE_STORE' => 'database'], "GO\n", 30);
        $process->mustRun();
        $lines = explode("\n", trim($process->getOutput()));
        $result = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['acquired' => 100, 'extra' => false, 'renewed' => true, 'count' => 100], $result);
    }

    public function test_cache_trigger_refuses_old_transaction_snapshots(): void
    {
        $configuration = config('database.connections.demo');
        $connection = new PDO('pgsql:host='.$configuration['host'].';port='.$configuration['port'].';dbname='.$configuration['database'],
            $configuration['username'], $configuration['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $connection->exec('BEGIN ISOLATION LEVEL REPEATABLE READ');
        try {
            $connection->exec("INSERT INTO cache(key, value, expiration) VALUES ('b38-old-snapshot', 'i:1;', extract(epoch FROM now())::integer+60)");
            $this->fail('Un ancien snapshot ne peut pas contourner la borne atomique.');
        } catch (PDOException $exception) {
            $this->assertSame('25000', $exception->getCode());
            $this->assertStringContainsString('B2_CACHE_ISOLATION', $exception->getMessage());
        } finally {
            $connection->exec('ROLLBACK');
        }
        $this->assertDatabaseCount('cache', 0);
    }

    public function test_cache_expiry_uses_integer_floor_not_early_fractional_rounding(): void
    {
        $row = DB::selectOne("SELECT floor(100.8::numeric)::bigint AS floored, 100.8::numeric::bigint AS rounded, pg_get_functiondef('demo_bound_cache'::regproc) AS definition");
        $this->assertNotNull($row);
        $this->assertSame(100, $row->floored);
        $this->assertSame(101, $row->rounded);
        $this->assertStringContainsString('floor(extract(epoch FROM clock_timestamp()))::bigint', $row->definition);
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
