<?php

use App\Data\Lab\TestEventData;
use App\Services\Lab\ProcessTestEventService;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));

$data = new TestEventData(
    runId: (string) $_SERVER['LAB_RUN_ID'],
    eventId: (string) $_SERVER['LAB_EVENT_ID'],
    orderRef: (string) $_SERVER['LAB_ORDER_REF'],
    amountMinor: (int) $_SERVER['LAB_AMOUNT_MINOR'],
    currency: (string) $_SERVER['LAB_CURRENCY'],
    receivedAt: new DateTimeImmutable('2026-10-05T12:00:00+00:00'),
);

echo "READY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    throw new RuntimeException('Barrière de test interrompue.');
}

$result = $app->make(ProcessTestEventService::class)->handle($data);
echo json_encode([
    'order_id' => $result->order->id,
    'source_event_id' => $result->order->source_event_id,
    'run_id' => $result->order->run_id,
    'order_ref' => $result->order->order_ref,
    'duplicate' => $result->duplicate,
], JSON_THROW_ON_ERROR)."\n";
