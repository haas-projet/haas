<?php

use App\Data\HelpRequests\ReopeningData;
use App\Data\HelpRequests\ResolutionData;
use App\Data\Idempotency\IdempotencyKey;
use App\Exceptions\HelpRequests\ResolutionConflict;
use App\Models\User;
use App\Services\HelpRequests\ReopenHelpRequestService;
use App\Services\HelpRequests\ResolveHelpRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b20_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_array($input) || array_diff(array_keys($input), ['actor_id', 'parent_id', 'proposal_id', 'operation', 'key', 'lock_version']) !== []
    || ! is_string($input['actor_id'] ?? null) || ! Str::isUuid($input['actor_id'])
    || ! is_string($input['parent_id'] ?? null) || ! Str::isUuid($input['parent_id'])
    || ! is_string($input['proposal_id'] ?? null) || ! Str::isUuid($input['proposal_id'])
    || ! is_string($input['key'] ?? null) || ! IdempotencyKey::valid($input['key'])
    || ! is_int($input['lock_version'] ?? null) || ! in_array($input['lock_version'], [4, 5], true)
    || ! in_array($input['operation'] ?? null, ['reopen', 'resolve'], true)) {
    throw new RuntimeException('Fixture de concurrence B20 invalide.');
}
try {
    $actor = User::findOrFail($input['actor_id']);
    $key = new IdempotencyKey($input['key']);
    if ($input['operation'] === 'reopen') {
        $fact = app(ReopenHelpRequestService::class)->reopen($actor, $input['parent_id'], new ReopeningData([
            'lock_version' => $input['lock_version'], 'reason' => 'Une réouverture fictive motivée pour une course PostgreSQL.',
        ]), $key);
        $result = ['status' => 'APPLIED', 'reopening_id' => $fact->id];
    } else {
        $resolution = app(ResolveHelpRequestService::class)->resolve($actor, $input['parent_id'], new ResolutionData([
            'proposal_id' => $input['proposal_id'], 'lock_version' => $input['lock_version'], 'proposal_lock_version' => 1,
            'environment' => 'Environnement fictif après réouverture.',
            'validation_note' => 'Une acceptation fictive après réouverture sous concurrence.',
        ]), $key);
        $result = ['status' => 'APPLIED', 'resolution_id' => $resolution->id];
    }
} catch (ResolutionConflict) {
    $result = ['status' => 'CONFLICT'];
} catch (AuthorizationException) {
    $result = ['status' => 'FORBIDDEN'];
} catch (ModelNotFoundException) {
    $result = ['status' => 'INVISIBLE'];
}
echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
