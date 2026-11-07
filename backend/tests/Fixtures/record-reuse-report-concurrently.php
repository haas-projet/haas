<?php

use App\Data\Capsules\ReuseReportData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\Capsules\ReuseReportOutcome;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\RecordReuseReportService;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b28_reuse_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_string($input['actor_id'] ?? null) || ! is_string($input['capsule_id'] ?? null) || ! is_string($input['version_id'] ?? null)) {
    throw new RuntimeException('Charge de fixture concurrente invalide.');
}
try {
    app(RecordReuseReportService::class)->handle(
        User::findOrFail($input['actor_id']),
        Capsule::whereKey($input['capsule_id'])->firstOrFail(),
        CapsuleVersion::whereKey($input['version_id'])->firstOrFail(),
        new ReuseReportData(
            environment: $input['environment'],
            reportedAt: new DateTimeImmutable($input['reported_at']),
            outcome: ReuseReportOutcome::from($input['outcome']),
            observation: $input['observation'],
        ),
        new IdempotencyKey($input['idempotency_key']),
    );
    echo "UPDATED\n";
} catch (AuthorizationException|IdempotencyConflict) {
    echo "CONFLICT\n";
}
