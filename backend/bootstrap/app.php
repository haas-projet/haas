<?php

use App\Http\Middleware\AssignRequestId;
use App\Support\Http\ApiExceptionRenderer;
use App\Support\Http\RequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/auth.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        then: static function (): void {
            require __DIR__.'/../routes/health.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => ApiExceptionRenderer::handles($request),
        );
        $exceptions->render(fn (Throwable $exception, Request $request) => app(ApiExceptionRenderer::class)->render($exception, $request));
        $exceptions->context(fn () => app()->bound('request')
            ? ['request_id' => RequestId::get(app(Request::class))]
            : []);
    })->create();
