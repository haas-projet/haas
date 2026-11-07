<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Exceptions\Capsules\StaleCapsuleVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Capsules\RequestChangesRequest;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\RequestChangesOnCapsuleVersionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RequestChangesController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(RequestChangesRequest $request, RequestChangesOnCapsuleVersionService $service, string $capsule, string $version): JsonResponse
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
            $review = $service->handle($actor, $target, $draft, $request->command(), $request->idempotencyKey());
        } catch (StaleCapsuleVersion $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse([
            'data' => [
                'id' => $review->id,
                'version_id' => $review->version_id,
                'decision' => $review->decision->value,
                'reviewed_lock_version' => $review->reviewed_lock_version,
                'note' => $review->note,
                'created_at' => $review->created_at?->toIso8601String(),
            ],
        ], 201, ['Cache-Control' => 'no-store, private']);
    }
}
