<?php

use App\Data\Collaboration\ProposalData;
use App\Data\Idempotency\IdempotencyKey;
use App\Exceptions\Collaboration\ProposalVersionConflict;
use App\Models\User;
use App\Services\Collaboration\ProposalService;
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
DB::statement("SET application_name = 'haas_b18_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_array($input) || array_diff(array_keys($input), ['actor_id', 'parent_id', 'proposal_id', 'create', 'key']) !== []
    || ! is_string($input['actor_id'] ?? null) || ! Str::isUuid($input['actor_id'])
    || ! is_string($input['parent_id'] ?? null) || ! Str::isUuid($input['parent_id'])
    || ! is_string($input['key'] ?? null) || ! is_bool($input['create'] ?? null)
    || (! $input['create'] && (! is_string($input['proposal_id'] ?? null) || ! Str::isUuid($input['proposal_id'])))) {
    throw new RuntimeException('Fixture de concurrence B18 invalide.');
}
try {
    $actor = User::findOrFail($input['actor_id']);
    $service = app(ProposalService::class);
    $key = new IdempotencyKey($input['key']);
    if ($input['create']) {
        $service->create($actor, $input['parent_id'], new ProposalData([
            'diagnosis' => 'Diagnostic fictif décrivant une cause probable.',
            'fix' => 'Correctif fictif proposé pour cette fixture.',
            'verification' => 'Vérification déclarée sur des données fictives.',
            'limits' => 'Limites fictives connues, aucun résultat certifié.',
            'code' => "    // Code fictif et inerte.\n    <script>aucune exécution</script>\n",
        ]), $key);
    } else {
        $service->update($actor, $input['proposal_id'], new ProposalData(['fix' => 'Correctif fictif clarifié après deux appels simultanés.'], 1), $key);
    }
    echo "APPLIED\n";
} catch (ProposalVersionConflict) {
    echo "CONFLICT\n";
} catch (AuthorizationException|ModelNotFoundException) {
    echo "FORBIDDEN\n";
}
