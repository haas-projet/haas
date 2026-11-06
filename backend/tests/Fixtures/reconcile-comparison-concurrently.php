<?php

use App\Services\Comparisons\ReconcileComparisonsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_bv208_reconciler'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_array($input) || ! is_string($input['comparison_id'] ?? null)) {
    throw new RuntimeException('Entrée de fixture invalide.');
}
try {
    $report = app(ReconcileComparisonsService::class)->reconcile($input['comparison_id']);
    $state = $report->state === null ? '-' : $report->state->value;
    $outcome = $report->exposedOutcome === null ? '-' : $report->exposedOutcome->value;
    echo $report->status.' '.$state.' '.$outcome."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.get_class($e)."\n";
}
