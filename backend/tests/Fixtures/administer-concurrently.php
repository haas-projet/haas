<?php

use App\Data\Identity\ManageAccountData;
use App\Exceptions\Identity\AccountVersionConflict;
use App\Models\User;
use App\Services\Identity\ManageAccountService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b32_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_string($input['user_id'])) {
    throw new RuntimeException('Identifiant de fixture invalide.');
}
try {
    app(ManageAccountService::class)->update(User::findOrFail($input['user_id']), $input['user_id'], new ManageAccountData(0, 'role', 'member', 'Décision de test concurrent uniquement.'));
    echo "UPDATED\n";
} catch (AccountVersionConflict) {
    echo "CONFLICT\n";
}
