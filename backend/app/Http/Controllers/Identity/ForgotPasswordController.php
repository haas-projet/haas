<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ForgotPasswordRequest;
use App\Http\Resources\Identity\AccountMailResource;
use App\Services\Identity\RequestPasswordResetService;
use Illuminate\Http\JsonResponse;

final class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request, RequestPasswordResetService $service): JsonResponse
    {
        $service->request($request->resetLinkData());

        return (new AccountMailResource('Si un compte peut recevoir ce lien, un courriel de réinitialisation sera envoyé.'))->response()->setStatusCode(202);
    }
}
