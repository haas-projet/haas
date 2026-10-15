<?php

declare(strict_types=1);

namespace App\Http\Controllers\VerificationCases;

use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Exceptions\VerificationCases\StaleVerificationCase;
use App\Exceptions\VerificationCases\VerificationCaseNotFound;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerificationCases\SubmitVerificationCaseRequest;
use App\Http\Resources\VerificationCases\VerificationCaseResource;
use App\Models\User;
use App\Services\VerificationCases\SubmitVerificationCaseService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SubmitVerificationCaseController extends Controller
{
    public function __invoke(
        SubmitVerificationCaseRequest $request,
        string $verification_case,
        SubmitVerificationCaseService $service,
    ): JsonResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        try {
            $case = $service->handle($actor, $verification_case, $request->toSubmit(), $request->idempotencyKey());
        } catch (IdempotencyConflict|StaleVerificationCase $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        } catch (VerificationCaseNotFound $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        return (new VerificationCaseResource($case))
            ->response($request)
            ->header('Cache-Control', 'no-store, private');
    }
}
