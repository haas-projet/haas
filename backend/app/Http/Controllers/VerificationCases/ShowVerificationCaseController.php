<?php

declare(strict_types=1);

namespace App\Http\Controllers\VerificationCases;

use App\Exceptions\VerificationCases\VerificationCaseNotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\VerificationCases\VerificationCaseResource;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Policies\VerificationCases\VerificationCasePolicy;
use App\Support\VerificationCases\VerificationCaseParentVisibility;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowVerificationCaseController extends Controller
{
    public function __invoke(
        Request $request,
        string $verification_case,
        VerificationCasePolicy $policy,
        VerificationCaseParentVisibility $visibility,
    ): JsonResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        $case = VerificationCase::query()
            ->whereKey($verification_case)
            ->with(['author:id,handle', 'revisions' => fn ($q) => $q->orderBy('revision_no')])
            ->first();
        if ($case === null || ! $policy->view($actor, $case)) {
            throw new NotFoundHttpException;
        }
        try {
            if ($case->help_request_id !== null) {
                $visibility->assertHelpRequestVisible($actor, $case->help_request_id);
            } elseif ($case->capsule_version_id !== null) {
                $visibility->assertCapsuleVersionVisible($actor, $case->capsule_version_id);
            }
        } catch (VerificationCaseNotFound $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        return (new VerificationCaseResource($case))
            ->response($request)
            ->header('Cache-Control', 'no-store, private');
    }
}
