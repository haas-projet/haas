<?php

use App\Data\Demo\DemoOrderData;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Services\Demo\RecordDemoOrderService;
use App\Support\Demo\DemoRuntimeGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\DemoTestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../demo/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DemoTestDatabaseGuard::check(config('app.env'), config('database.connections.demo'));
DB::statement("SET application_name = 'b38_concurrency_child'");
echo "READY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    throw new RuntimeException('Barrière interrompue.');
}
try {
    if (($_SERVER['DEMO_TEST_MODE'] ?? '') === 'cache_locks') {
        app(DemoRuntimeGuard::class)->database();
        try {
            $acquired = 0;
            for ($index = 0; $index < 100; $index++) {
                $acquired += Cache::lock('b38-lock-capacity-'.$index, 60, 'fictif')->get() ? 1 : 0;
            }
            echo json_encode(['acquired' => $acquired, 'extra' => Cache::lock('b38-lock-capacity-extra', 60, 'fictif')->get(),
                'renewed' => Cache::lock('b38-lock-capacity-0', 60, 'fictif')->get(), 'count' => DB::table('cache_locks')->count()], JSON_THROW_ON_ERROR)."\n";
        } finally {
            DB::table('cache_locks')->where('key', 'like', 'haas-b2-b38-lock-capacity-%')->delete();
        }
        exit(0);
    }
    if (($_SERVER['DEMO_TEST_MODE'] ?? 'order') === 'cache') {
        app(DemoRuntimeGuard::class)->database();
        Cache::put((string) $_SERVER['DEMO_TEST_CACHE_KEY'], 1, 60);
        echo json_encode(['status' => 200], JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    $result = $app->make(RecordDemoOrderService::class)->handle(new DemoOrderData('demo-0001', (int) $_SERVER['DEMO_TEST_AMOUNT'], 'EUR'), (string) $_SERVER['DEMO_TEST_KEY']);
    echo json_encode(['status' => 200, 'id' => $result->order->id, 'replay' => $result->replay], JSON_THROW_ON_ERROR)."\n";
} catch (IdempotencyConflict) {
    echo json_encode(['status' => 409], JSON_THROW_ON_ERROR)."\n";
} catch (QueryException $exception) {
    if (($exception->errorInfo[0] ?? null) !== 'P0001' || ! str_contains((string) ($exception->errorInfo[2] ?? ''), 'B2_CACHE_CAPACITY')) {
        throw $exception;
    }
    echo json_encode(['status' => 429], JSON_THROW_ON_ERROR)."\n";
}
