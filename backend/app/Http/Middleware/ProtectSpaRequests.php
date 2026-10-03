<?php

namespace App\Http\Middleware;

use App\Support\Identity\SpaConfiguration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ProtectSpaRequests
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $authPath = $request->is('login', 'logout', 'register', 'sanctum/csrf-cookie');
        if ($authPath || $request->is('api', 'api/*')) {
            app(SpaConfiguration::class)->validate();
            $allowed = [config('spa.frontend_url'), config('app.url')];
            if ($request->headers->has('Origin') && ! in_array($request->header('Origin'), $allowed, true)) {
                throw new AccessDeniedHttpException;
            }
            if ($request->headers->has('Referer')) {
                $referer = parse_url((string) $request->header('Referer'));
                $origin = is_array($referer) ? ($referer['scheme'] ?? '').'://'.($referer['host'] ?? '').(isset($referer['port']) ? ':'.$referer['port'] : '') : '';
                if (! in_array($origin, $allowed, true) || isset($referer['user']) || isset($referer['pass'])) {
                    throw new AccessDeniedHttpException;
                }
            }
        }
        $response = $next($request);
        if ($authPath) {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
