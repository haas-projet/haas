<?php

use App\Data\HelpRequests\CreateHelpRequestData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\HelpRequests\HelpIntent;
use App\Enums\HelpRequests\SubmissionMode;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Models\User;
use App\Services\HelpRequests\CreateHelpRequestService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b14_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_string($input['user_id']) || ! is_string($input['title'])) {
    throw new RuntimeException('Fixture de création invalide.');
}
try {
    $data = new CreateHelpRequestData(SubmissionMode::Draft, HelpIntent::Unblock, [
        'title' => $input['title'], 'goal' => null, 'expected' => null, 'observed' => null, 'attempts' => null,
        'environment' => null, 'code' => null, 'code_language' => null, 'primary_language' => 'fr', 'reproduction_url' => null,
    ], []);
    $request = app(CreateHelpRequestService::class)->create(User::findOrFail($input['user_id']), $data, new IdempotencyKey('fda9a000-3333-4444-8888-123456789abc'));
    echo 'CREATED:'.$request->id."\n";
} catch (IdempotencyConflict) {
    echo "CONFLICT\n";
}
