<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Exceptions\Capsules\InsufficientDraftContent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Capsules\CapsuleVersionDraftResource;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\SubmitCapsuleVersionForReviewService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SubmitCapsuleVersionForReviewController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(Request $request, SubmitCapsuleVersionForReviewService $service, string $capsule, string $version): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        $target = Capsule::whereKey($capsule)->firstOrFail();
        $draft = CapsuleVersion::whereKey($version)->where('capsule_id', $target->id)->first();
        if ($draft === null) {
            throw new NotFoundHttpException;
        }
        try {
            $updated = $service->handle($actor, $target, $draft);
        } catch (InsufficientDraftContent $e) {
            throw ValidationException::withMessages([
                'content' => explode('|', $e->getMessage()),
            ]);
        }

        return (new CapsuleVersionDraftResource($updated))->response()->setStatusCode(200);
    }
}
