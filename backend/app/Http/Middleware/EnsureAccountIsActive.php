<?php

namespace App\Http\Middleware;

use App\Enums\Identity\AccountStatus;
use App\Exceptions\Identity\InactiveAccount;
use App\Models\User;
use App\Support\Http\MemberSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountIsActive
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() instanceof User && $request->user()->status !== AccountStatus::Active) {
            app(MemberSession::class)->end($request);
            // L'information de recours reste disponible après révocation de la session.
            if (! $request->routeIs('account.access')) {
                throw new InactiveAccount;
            }
        }
        try {
            $response = $next($request);
        } catch (InactiveAccount $exception) {
            app(MemberSession::class)->end($request);
            throw $exception;
        }
        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
