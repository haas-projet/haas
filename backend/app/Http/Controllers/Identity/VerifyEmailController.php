<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\VerifyEmailRequest;
use App\Http\Resources\Identity\SessionMemberResource;
use App\Services\Identity\VerifyEmailService;

final class VerifyEmailController extends Controller
{
    public function __invoke(VerifyEmailRequest $request, VerifyEmailService $service): SessionMemberResource
    {
        return new SessionMemberResource($service->verify($request->member(), $request->verificationData()));
    }
}
