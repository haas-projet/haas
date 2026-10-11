<?php

use App\Data\Capsules\CapsuleDraftData;
use App\Data\Capsules\UpdateDraftData;
use App\Data\Capsules\VersionDraftData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\Capsules\CapsuleSourceKind;
use App\Exceptions\Capsules\CapsuleDraftConflict;
use App\Exceptions\Capsules\StaleCapsuleVersion;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\CreateCapsuleDraftService;
use App\Services\Capsules\UpdateCapsuleVersionDraftService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b23_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_array($input) || ! is_string($input['actor_id'] ?? null) || ! is_string($input['key'] ?? null)
    || ! in_array($input['command'] ?? null, ['create', 'source', 'edit'], true)) {
    throw new RuntimeException('Fixture B23 invalide.');
}
try {
    $actor = User::findOrFail($input['actor_id']);
    if ($input['command'] === 'edit') {
        if (! is_string($input['capsule_id'] ?? null) || ! is_string($input['version_id'] ?? null)) {
            throw new RuntimeException('Cible de brouillon B23 invalide.');
        }
        app(UpdateCapsuleVersionDraftService::class)->handle($actor, Capsule::findOrFail($input['capsule_id']), CapsuleVersion::findOrFail($input['version_id']),
            new UpdateDraftData(1, 'Procédure fictive corrigée sous concurrence.', null, null));
    } else {
        $source = $input['command'] === 'source';
        app(CreateCapsuleDraftService::class)->handle($actor,
            new CapsuleDraftData('concurrence-capsule', $source ? CapsuleSourceKind::HelpRequest : CapsuleSourceKind::Editorial,
                $source ? $input['source_id'] : null, $source ? null : 'Exemple fictif de concurrence',
                new VersionDraftData('1.0.0', 'Procédure fictive créée sous concurrence.', 'Limites déclarées du scénario.', [])),
            new IdempotencyKey($input['key']));
    }
    echo "APPLIED\n";
} catch (CapsuleDraftConflict|StaleCapsuleVersion) {
    echo "CONFLICT\n";
} catch (AuthorizationException) {
    echo "FORBIDDEN\n";
} catch (ValidationException) {
    echo "INVALID\n";
}
