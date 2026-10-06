<?php

use App\Data\Notifications\NotificationEvent;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b29_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_string($input['user_id'])) {
    throw new RuntimeException('Identifiant de fixture invalide.');
}
DB::transaction(fn () => app(NotificationOutbox::class)->record(new NotificationEvent($input['event_id'], $input['user_id'], 'profile.moderated')));
echo "RECORDED\n";
