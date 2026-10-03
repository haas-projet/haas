<?php

namespace App\Support\Http;

use App\Models\User;
use Illuminate\Auth\RequestGuard;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;

final class MemberSession
{
    public function start(Request $request, User $user): void
    {
        $guard = Auth::guard('web');
        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Le garde web doit utiliser les sessions Laravel.');
        }
        $guard->login($user, false);
        // Lie immédiatement la session au secret vérifié, même si un reset devance son écriture SQL.
        $request->session()->put('password_hash_web', $guard->hashPasswordForCookie($user->getAuthPassword()));
        $request->session()->regenerate();
    }

    public function end(Request $request): void
    {
        Auth::guard('web')->logout();
        $apiGuard = Auth::guard('sanctum');
        if ($apiGuard instanceof RequestGuard) {
            $apiGuard->forgetUser();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
