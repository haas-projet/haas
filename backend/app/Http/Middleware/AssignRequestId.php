<?php

namespace App\Http\Middleware;

use App\Support\Http\RequestId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AssignRequestId
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $id = RequestId::get($request);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);

        return $response;
    }
}
