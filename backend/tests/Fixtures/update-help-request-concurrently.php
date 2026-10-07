<?php

use App\Data\HelpRequests\UpdateHelpRequestData;
use App\Data\Idempotency\IdempotencyKey;
use App\Exceptions\HelpRequests\HelpRequestVersionConflict;
use App\Models\User;
use App\Services\HelpRequests\UpdateHelpRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b16_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_string($input['user_id']) || ! is_string($input['request_id']) || ! is_bool($input['publish']) || ! is_string($input['key'])) {
    throw new RuntimeException('Fixture B16 invalide.');
}
try {
    $actor = User::findOrFail($input['user_id']);
    $key = new IdempotencyKey($input['key']);
    $service = app(UpdateHelpRequestService::class);
    if ($input['publish']) {
        $service->publish($actor, $input['request_id'], 1, $key);
    } else {
        $service->update($actor, $input['request_id'], new UpdateHelpRequestData(1, ['title' => 'Modification concurrente du contexte']), $key);
    }
    echo "APPLIED\n";
} catch (HelpRequestVersionConflict) {
    echo "CONFLICT\n";
} catch (AuthorizationException) {
    echo "FORBIDDEN\n";
} catch (ValidationException) {
    echo "INVALID\n";
}
