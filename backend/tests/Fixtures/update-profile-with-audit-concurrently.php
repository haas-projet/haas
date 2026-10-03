<?php

use App\Data\Identity\UpdateProfileData;
use App\Models\User;
use App\Services\Identity\UpdateProfileService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b12_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_string($input['user_id'])) {
    throw new RuntimeException('Identifiant de fixture invalide.');
}
DB::transaction(function () use ($input): void {
    $user = User::whereKey($input['user_id'])->lockForUpdate()->firstOrFail();
    $profile = $user->profile()->firstOrFail();
    app(UpdateProfileService::class)->update($user, new UpdateProfileData($profile->lock_version, ['bio' => $input['bio']], null));
});
echo "UPDATED\n";
