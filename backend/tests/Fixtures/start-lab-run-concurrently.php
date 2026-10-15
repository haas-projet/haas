<?php

use App\Data\Lab\StartLabRunData;
use App\Enums\Lab\LabScenario;
use App\Exceptions\Lab\LabCapacityReached;
use App\Models\User;
use App\Services\Lab\StartLabRunService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
config(['lab.enabled' => true]);
DB::statement("SET application_name = 'haas_b34_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_array($input) || ! is_string($input['user_id'] ?? null) || ! is_string($input['version_id'] ?? null) || ! is_string($input['scenario'] ?? null) || ! is_string($input['key'] ?? null)) {
    throw new RuntimeException('Entrée de fixture invalide.');
}
$user = User::query()->findOrFail($input['user_id']);
try {
    app(StartLabRunService::class)->handle(
        $user,
        new StartLabRunData($input['version_id'], LabScenario::from($input['scenario'])),
        $input['key'],
    );
    echo "ACCEPTED\n";
} catch (LabCapacityReached) {
    echo "CAPACITY\n";
} catch (Throwable $e) {
    echo 'ERROR: '.get_class($e)."\n";
}
