<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\ProtectSpaRequests;
use App\Http\Middleware\RequireCsrfToken;
use App\Http\Middleware\RequireVerifiedEmail;
use App\Support\Http\ApiExceptionRenderer;
use App\Support\Http\RequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

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
        $middleware->trimStrings(except: ['code', 'body', 'version.body']);
        $middleware->append(ProtectSpaRequests::class);
        $middleware->statefulApi();
        $middleware->alias(['verified' => RequireVerifiedEmail::class]);
        $middleware->web(append: [AuthenticateSession::class, EnsureAccountIsActive::class], replace: [PreventRequestForgery::class => RequireCsrfToken::class]);
        $middleware->api(append: [EnsureAccountIsActive::class]);
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
