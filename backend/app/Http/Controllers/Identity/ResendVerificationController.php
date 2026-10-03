<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ResendVerificationRequest;
use App\Http\Resources\Identity\AccountMailResource;
use App\Services\Identity\ResendVerificationService;
use Illuminate\Http\JsonResponse;

final class ResendVerificationController extends Controller
{
    public function __invoke(ResendVerificationRequest $request, ResendVerificationService $service): JsonResponse
    {
        $service->resend($request->member());

        return (new AccountMailResource('Si votre courriel reste à vérifier, un lien de vérification sera envoyé.'))->response()->setStatusCode(202);
    }
}
