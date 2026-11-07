<?php

declare(strict_types=1);

namespace App\Http\Controllers\VerificationCases;

use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Exceptions\VerificationCases\VerificationCaseNotFound;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerificationCases\StoreVerificationCaseRequest;
use App\Http\Resources\VerificationCases\VerificationCaseResource;
use App\Models\User;
use App\Services\VerificationCases\CreateVerificationCaseService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StoreVerificationCaseController extends Controller
{
    public function __invoke(StoreVerificationCaseRequest $request, CreateVerificationCaseService $service): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        try {
            $case = $service->handle($actor, $request->toCreate(), $request->idempotencyKey());
        } catch (IdempotencyConflict $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        } catch (VerificationCaseNotFound $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        return (new VerificationCaseResource($case))
            ->response($request)
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Cache-Control', 'no-store, private');
    }
}
