<?php

use App\Data\Collaboration\ProposalData;
use App\Data\HelpRequests\ResolutionData;
use App\Data\Idempotency\IdempotencyKey;
use App\Exceptions\Collaboration\ProposalVersionConflict;
use App\Exceptions\HelpRequests\ResolutionConflict;
use App\Models\User;
use App\Services\Collaboration\ProposalService;
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
DB::statement("SET application_name = 'haas_b19_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_array($input) || array_diff(array_keys($input), ['actor_id', 'parent_id', 'proposal_id', 'operation', 'key']) !== []
    || ! is_string($input['actor_id'] ?? null) || ! Str::isUuid($input['actor_id'])
    || ! is_string($input['parent_id'] ?? null) || ! Str::isUuid($input['parent_id'])
    || ! is_string($input['proposal_id'] ?? null) || ! Str::isUuid($input['proposal_id'])
    || ! is_string($input['key'] ?? null) || ! IdempotencyKey::valid($input['key'])
    || ! in_array($input['operation'] ?? null, ['resolve', 'edit'], true)) {
    throw new RuntimeException('Fixture de concurrence B19 invalide.');
}
try {
    $actor = User::findOrFail($input['actor_id']);
    $key = new IdempotencyKey($input['key']);
    if ($input['operation'] === 'resolve') {
        $resolution = app(ResolveHelpRequestService::class)->resolve($actor, $input['parent_id'], new ResolutionData([
            'proposal_id' => $input['proposal_id'], 'lock_version' => 1, 'proposal_lock_version' => 1,
            'environment' => 'Environnement local fictif PHP et PostgreSQL.',
            'validation_note' => 'Observation fictive : cette proposition a résolu le blocage décrit.',
        ]), $key);
        $result = ['status' => 'APPLIED', 'resolution_id' => $resolution->id];
    } else {
        $proposal = app(ProposalService::class)->update($actor, $input['proposal_id'], new ProposalData([
            'fix' => 'Correctif fictif clarifié avant une acceptation concurrente.',
        ], 1), $key);
        $result = ['status' => 'APPLIED', 'proposal_id' => $proposal->id];
    }
} catch (ResolutionConflict|ProposalVersionConflict) {
    $result = ['status' => 'CONFLICT'];
} catch (AuthorizationException) {
    $result = ['status' => 'FORBIDDEN'];
} catch (ModelNotFoundException) {
    $result = ['status' => 'INVISIBLE'];
}
echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
