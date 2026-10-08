<?php

use App\Data\Capsules\ReviewCommandData;
use App\Data\Idempotency\IdempotencyKey;
use App\Exceptions\Capsules\StaleCapsuleVersion;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\RequestChangesOnCapsuleVersionService;
use App\Services\Capsules\SubmitCapsuleVersionForReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b24_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_array($input) || ! is_string($input['actor_id'] ?? null) || ! is_string($input['key'] ?? null)
    || ! is_string($input['capsule_id'] ?? null) || ! is_string($input['version_id'] ?? null)
    || ! in_array($input['command'] ?? null, ['submit', 'review'], true)) {
    throw new RuntimeException('Fixture B24 invalide.');
}
try {
    $actor = User::findOrFail($input['actor_id']);
    $capsule = Capsule::findOrFail($input['capsule_id']);
    $version = CapsuleVersion::findOrFail($input['version_id']);
    $key = new IdempotencyKey($input['key']);
    if ($input['command'] === 'submit') {
        app(SubmitCapsuleVersionForReviewService::class)->handle($actor, $capsule, $version, new ReviewCommandData(1), $key);
    } else {
        app(RequestChangesOnCapsuleVersionService::class)->handle($actor, $capsule, $version, new ReviewCommandData(1, 'Précisez les étapes et les limites de cette procédure.'), $key);
    }
    echo "APPLIED\n";
} catch (StaleCapsuleVersion) {
    echo "CONFLICT\n";
} catch (AuthorizationException) {
    echo "FORBIDDEN\n";
}
