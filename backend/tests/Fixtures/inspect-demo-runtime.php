<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../demo/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
echo json_encode([
    'key_absent' => config('app.key') === null,
    'database_connections' => array_keys(config('database.connections')),
    'db_password_independent' => config('database.connections.demo.password') !== 'sentinelle-fictive',
    'routes' => array_map(fn ($route) => $route->uri(), Route::getRoutes()->getRoutes()),
    'config_cache' => $app->getCachedConfigPath(), 'routes_cache' => $app->getCachedRoutesPath(),
    'packages_cache' => $app->getCachedPackagesPath(), 'services_cache' => $app->getCachedServicesPath(),
    'events_cache' => $app->getCachedEventsPath(), 'storage' => $app->storagePath(),
], JSON_THROW_ON_ERROR);
