<?php

declare(strict_types=1);

use App\Data\Idempotency\IdempotencyKey;
use App\Data\VerificationCases\ReviseVerificationCaseData;
use App\Exceptions\VerificationCases\StaleVerificationCase;
use App\Models\User;
use App\Services\VerificationCases\ReviseVerificationCaseService;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));

echo "READY\n";
flush();
$signal = fgets(STDIN);
if ($signal === false) {
    exit(1);
}
$payload = json_decode(trim($signal), true, flags: JSON_THROW_ON_ERROR);
if (! is_array($payload) || ! isset($payload['user_id'], $payload['case_id'], $payload['lock_version'], $payload['body'], $payload['idempotency_key'])) {
    echo "BAD_INPUT\n";
    exit(1);
}
$actor = User::whereKey($payload['user_id'])->firstOrFail();
try {
    app(ReviseVerificationCaseService::class)->handle(
        $actor,
        (string) $payload['case_id'],
        new ReviseVerificationCaseData((int) $payload['lock_version'], $payload['body']),
        new IdempotencyKey((string) $payload['idempotency_key']),
    );
    echo "ACCEPTED\n";
} catch (StaleVerificationCase) {
    echo "STALE\n";
} catch (Throwable $e) {
    echo 'ERROR: '.get_class($e)."\n";
}
