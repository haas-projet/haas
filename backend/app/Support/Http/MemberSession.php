<?php

namespace App\Support\Http;

use App\Models\User;
use Illuminate\Auth\RequestGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class MemberSession
{
    public function start(Request $request, User $user): void
    {
        Auth::guard('web')->login($user, false);
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
