<?php

use App\Data\Identity\UpdateProfileData;
use App\Exceptions\Identity\ProfileVersionConflict;
use App\Models\User;
use App\Services\Identity\UpdateProfileService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b10_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_string($input['user_id'])) {
    throw new RuntimeException('Identifiant de fixture invalide.');
}
try {
    app(UpdateProfileService::class)->update(User::findOrFail($input['user_id']), new UpdateProfileData(0, ['bio' => $input['bio']], $input['technologies']));
    echo "UPDATED\n";
} catch (ProfileVersionConflict) {
    echo "CONFLICT\n";
}
