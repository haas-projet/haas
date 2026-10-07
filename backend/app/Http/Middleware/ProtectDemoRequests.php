<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ProtectDemoRequests
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = config('demo.frontend_origin');
        $api = config('app.url');
        $parent = config('demo.haas_cookie_parent');
        foreach ([$origin, $api] as $url) {
            $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
            if (! is_string($host) || ! is_string($parent) || $host === $parent || str_ends_with($host, '.'.$parent)) {
                throw new HttpException(503);
            }
        }
        if ($request->getSchemeAndHttpHost() !== $api
            || ($request->headers->has('Origin') && $request->header('Origin') !== $origin)
            || $request->headers->has('Cookie') || $request->cookies->count() > 0
            || $request->headers->has('Authorization')
        ) {
            throw new AccessDeniedHttpException;
        }
        if ($request->headers->has('Referer')) {
            $parts = parse_url((string) $request->header('Referer'));
            $refOrigin = is_array($parts) ? ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '') : '';
            if ($refOrigin !== $origin || isset($parts['user']) || isset($parts['pass'])) {
                throw new AccessDeniedHttpException;
            }
        }
        if ($request->isMethod('POST')) {
            if (! $request->isJson()) {
                throw new HttpException(415);
            }
            if (strlen($request->getContent()) > 2048) {
                throw new HttpException(413);
            }
        }

        return $next($request);
    }
}
