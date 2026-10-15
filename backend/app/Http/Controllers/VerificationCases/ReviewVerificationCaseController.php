<?php

declare(strict_types=1);

namespace App\Http\Controllers\VerificationCases;

use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Exceptions\VerificationCases\StaleVerificationCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerificationCases\ReviewVerificationCaseRequest;
use App\Http\Resources\VerificationCases\VerificationCaseResource;
use App\Models\User;
use App\Services\VerificationCases\ReviewVerificationCaseService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReviewVerificationCaseController extends Controller
{
    public function __invoke(
        ReviewVerificationCaseRequest $request,
        string $verification_case,
        ReviewVerificationCaseService $service,
    ): JsonResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        try {
            $case = $service->handle($actor, $verification_case, $request->toReview(), $request->idempotencyKey());
        } catch (IdempotencyConflict|StaleVerificationCase $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        }

        return (new VerificationCaseResource($case))
            ->response($request)
            ->header('Cache-Control', 'no-store, private');
    }
}
