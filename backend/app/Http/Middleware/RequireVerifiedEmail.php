<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireVerifiedEmail
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof User || ! $request->user()->hasVerifiedEmail()) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
