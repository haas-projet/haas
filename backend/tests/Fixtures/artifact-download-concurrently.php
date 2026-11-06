<?php

declare(strict_types=1);

use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Models\Capsules\Artifact;
use App\Models\User;
use App\Services\Capsules\IssueArtifactDownloadService;
use App\Services\Capsules\StreamArtifactService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b27_worker'");
echo "READY\n";
flush();
$line = fgets(STDIN);
$input = json_decode($line === false ? '' : $line, true, 512, JSON_THROW_ON_ERROR);
if (! is_array($input)) {
    exit(2);
}
foreach (['actor', 'capsule', 'version', 'artifact'] as $field) {
    if (! isset($input[$field]) || ! is_string($input[$field])
        || preg_match('/^[0-9a-f-]{36}$/D', $input[$field]) !== 1) {
        exit(2);
    }
}
$root = storage_path('framework/testing/disks/local');
config(['filesystems.disks.local.root' => $root]);
try {
    $actor = User::findOrFail($input['actor']);
    if (($input['command'] ?? '') === 'deactivate') {
        Artifact::whereKey($input['artifact'])->update(['distribution_status' => 'inactive']);
    } elseif (($input['command'] ?? '') === 'stream') {
        $app->make(StreamArtifactService::class)->handle($actor, $input['capsule'], $input['version'], $input['artifact'])->close();
    } else {
        $app->make(IssueArtifactDownloadService::class)->handle($actor, $input['capsule'], $input['version'], $input['artifact']);
    }
    echo "APPLIED\n";
} catch (ArtifactUnavailable) {
    echo "UNAVAILABLE\n";
} catch (Throwable $e) {
    echo 'ERROR:'.get_class($e).($e instanceof QueryException ? ':'.$e->getCode() : '')."\n";
    exit(2);
}
