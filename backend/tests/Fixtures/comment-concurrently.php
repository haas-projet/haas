<?php

use App\Data\Collaboration\CommentData;
use App\Data\Idempotency\IdempotencyKey;
use App\Exceptions\Collaboration\CommentVersionConflict;
use App\Models\User;
use App\Services\Collaboration\CommentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b17_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_string($input['actor_id']) || ! is_string($input['parent_id']) || ! is_string($input['key'])
    || ! is_bool($input['create']) || (! $input['create'] && ! is_string($input['comment_id']))) {
    throw new RuntimeException('Fixture de concurrence B17 invalide.');
}
try {
    $actor = User::findOrFail($input['actor_id']);
    $service = app(CommentService::class);
    $key = new IdempotencyKey($input['key']);
    if ($input['create']) {
        $service->create($actor, $input['parent_id'], new CommentData('Commentaire fictif simultané'), $key);
    } else {
        $service->update($actor, $input['comment_id'], new CommentData('Commentaire fictif clarifié', 1), $key);
    }
    echo "APPLIED\n";
} catch (CommentVersionConflict) {
    echo "CONFLICT\n";
} catch (AuthorizationException|ModelNotFoundException) {
    echo "FORBIDDEN\n";
}
