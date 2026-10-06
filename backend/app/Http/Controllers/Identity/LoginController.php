<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\LoginRequest;
use App\Http\Resources\Identity\SessionMemberResource;
use App\Services\Identity\AuthenticateMemberService;
use App\Support\Http\MemberSession;

final class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, AuthenticateMemberService $service, MemberSession $session): SessionMemberResource
    {
        $user = $service->authenticate($request->credentialsData());
        $session->start($request, $user);

        return new SessionMemberResource($user);
    }
}
