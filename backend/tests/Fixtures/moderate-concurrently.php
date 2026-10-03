<?php

use App\Data\Moderation\ReportData;
use App\Data\Moderation\ReportDecisionData;
use App\Exceptions\ModerationRejected;
use App\Models\User;
use App\Services\Moderation\CreateReportService;
use App\Services\Moderation\DecideReportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b30_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_string($input['user_id'])) {
    throw new RuntimeException('Identifiant de fixture invalide.');
}
try {
    $actor = User::findOrFail($input['user_id']);
    if ($input['mode'] === 'decision') {
        app(DecideReportService::class)->decide($actor, $input['target_id'], new ReportDecisionData(0, 'hide', 'Décision de test concurrent uniquement.', 0));
    } else {
        app(CreateReportService::class)->create($actor, new ReportData($input['target_id'], 'other', 'Signalement de test concurrent uniquement.'));
    }

    echo "UPDATED\n";
} catch (ModerationRejected) {
    echo "CONFLICT\n";
}
