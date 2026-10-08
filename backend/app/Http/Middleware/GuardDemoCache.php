<?php

namespace App\Http\Middleware;

use App\Support\Demo\DemoRuntimeGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class GuardDemoCache
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && config('cache.default') === 'database') {
            app(DemoRuntimeGuard::class)->database();
        }

        return $next($request);
    }
}
