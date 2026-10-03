<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ResetPasswordRequest;
use App\Http\Resources\Identity\AccountMailResource;
use App\Services\Identity\ResetPasswordService;
use App\Support\Http\MemberSession;

final class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request, ResetPasswordService $service, MemberSession $session): AccountMailResource
    {
        $service->reset($request->passwordData());
        $session->end($request);

        return new AccountMailResource('Votre mot de passe a été réinitialisé. Connectez-vous avec le nouveau mot de passe.');
    }
}
