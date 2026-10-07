<?php

use App\Console\Commands\PruneDemoOrders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\ProtectDemoRequests;
use App\Providers\DemoServiceProvider;
use App\Support\Demo\DemoApplication;
use App\Support\Demo\DemoPackageManifest;
use App\Support\Http\ApiExceptionRenderer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Http\Request;

$app = DemoApplication::configure(basePath: dirname(__DIR__))
    ->withProviders([DemoServiceProvider::class], withBootstrapProviders: false)
    ->withRouting(api: __DIR__.'/../routes/api.php', apiPrefix: 'api/v1', commands: __DIR__.'/../routes/console.php')
    ->withCommands([PruneDemoOrders::class])
    ->withMiddleware(function (Middleware $middleware): void {
        // ProtectDemoRequests s'exécute avant CORS, y compris lors des prévols.
        $middleware->prepend([AssignRequestId::class, ProtectDemoRequests::class]);
        $middleware->remove([
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (): bool => true);
        $exceptions->render(fn (Throwable $exception, Request $request) => app(ApiExceptionRenderer::class)->render($exception, $request));
    })->create();

// Les valeurs par défaut du framework incluent des connexions et routes de stockage.
// Seule la configuration DEMO explicite est chargée.
$app->dontMergeFrameworkConfiguration();

// Les packages HAAS (dont Sanctum) ne sont jamais découverts dans ce runtime.
$app->instance(PackageManifest::class, new DemoPackageManifest(new Filesystem, $app->basePath(), $app->getCachedPackagesPath()));

return $app;
