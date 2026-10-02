<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\RegisterMemberRequest;
use App\Http\Resources\Identity\RegisteredMemberResource;
use App\Services\Identity\RegisterMemberService;

final class RegisterMemberController extends Controller
{
    public function __invoke(RegisterMemberRequest $request, RegisterMemberService $service): RegisteredMemberResource
    {
        return new RegisteredMemberResource($service->register($request->registrationData()));
    }
}
