<?php

namespace App\Http\Middleware;

use App\Enums\Identity\AccountStatus;
use App\Exceptions\Identity\InactiveAccount;
use App\Models\User;
use App\Support\Http\MemberSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveSession
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() instanceof User && $request->user()->status !== AccountStatus::Active) {
            app(MemberSession::class)->end($request);
            throw new InactiveAccount;
        }
        $response = $next($request);
        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
