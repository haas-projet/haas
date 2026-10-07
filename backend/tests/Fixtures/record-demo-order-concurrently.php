<?php

use App\Data\Demo\DemoOrderData;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Services\Demo\RecordDemoOrderService;
use Illuminate\Contracts\Console\Kernel;
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
    $result = $app->make(RecordDemoOrderService::class)->handle(new DemoOrderData('demo-0001', (int) $_SERVER['DEMO_TEST_AMOUNT'], 'EUR'), (string) $_SERVER['DEMO_TEST_KEY']);
    echo json_encode(['status' => 200, 'id' => $result->order->id, 'replay' => $result->replay], JSON_THROW_ON_ERROR)."\n";
} catch (IdempotencyConflict) {
    echo json_encode(['status' => 409], JSON_THROW_ON_ERROR)."\n";
}
