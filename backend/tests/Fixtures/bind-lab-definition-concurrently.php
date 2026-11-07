<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));

$version = getenv('B33_BIND_VERSION');
if (! is_string($version)) {
    exit(1);
}
$process = DB::connection('pgsql')->selectOne('SELECT pg_backend_pid() AS pid');
if ($process === null) {
    exit(1);
}
echo 'READY '.$process->pid."\n";
flush();
$signal = fgets(STDIN);
if ($signal === false || trim($signal) !== 'GO') {
    exit(1);
}
try {
    $code = Artisan::call('lab:bind-definition', ['version' => $version, 'release' => 'b1-source-20261011']);
    echo 'DONE '.$code."\n";
    exit($code);
} catch (Throwable) {
    // Never send a database trace or inherited environment through test output.
    echo "ERROR\n";
    exit(1);
}
