<?php

use App\Data\Idempotency\IdempotencyKey;
use App\Data\Projects\CreateProjectData;
use App\Data\Projects\UpdateProjectData;
use App\Exceptions\Projects\ProjectVersionConflict;
use App\Models\User;
use App\Services\Projects\CreateProjectService;
use App\Services\Projects\UpdateProjectService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_bc02_worker'");
echo "READY\n";
flush();
$packet = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_array($packet) || array_diff(array_keys($packet), ['actor_id', 'project_id', 'operation', 'key', 'name']) !== []
    || ! is_string($packet['actor_id'] ?? null) || ! Str::isUuid($packet['actor_id'])
    || ! is_string($packet['project_id'] ?? null) || ! Str::isUuid($packet['project_id'])
    || ! is_string($packet['key'] ?? null) || ! IdempotencyKey::valid($packet['key'])
    || ! in_array($packet['operation'] ?? null, ['create', 'update'], true)
    || ! in_array($packet['name'] ?? null, ['Projet processus A', 'Projet processus B'], true)) {
    throw new RuntimeException('Fixture de concurrence BC02 invalide.');
}
try {
    $actor = User::findOrFail($packet['actor_id']);
    $key = new IdempotencyKey($packet['key']);
    $project = $packet['operation'] === 'create'
        ? app(CreateProjectService::class)->create($actor, new CreateProjectData(['name' => $packet['name']]), $key)
        : app(UpdateProjectService::class)->update($actor, $packet['project_id'], new UpdateProjectData(1, ['name' => $packet['name']]), $key);
    $result = ['status' => 'APPLIED', 'project_id' => $project->id, 'version' => $project->lock_version];
} catch (ProjectVersionConflict) {
    $result = ['status' => 'CONFLICT'];
} catch (AuthorizationException) {
    $result = ['status' => 'FORBIDDEN'];
}
echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
