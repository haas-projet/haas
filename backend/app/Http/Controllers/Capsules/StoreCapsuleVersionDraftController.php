<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Exceptions\Capsules\CapsuleDraftConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Capsules\StoreCapsuleVersionDraftRequest;
use App\Http\Resources\Capsules\CapsuleVersionDraftResource;
use App\Models\Capsules\Capsule;
use App\Models\User;
use App\Services\Capsules\CreateCapsuleVersionDraftService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class StoreCapsuleVersionDraftController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(StoreCapsuleVersionDraftRequest $request, CreateCapsuleVersionDraftService $service, string $capsule): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        $target = Capsule::whereKey($capsule)->firstOrFail();
        try {
            $result = $service->handle(
                actor: $actor,
                capsule: $target,
                draft: $request->toDraft(),
                key: $request->idempotencyKey(),
            );
        } catch (CapsuleDraftConflict $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        }
        $version = $result['version'];
        $resource = new CapsuleVersionDraftResource($version);

        return $resource->response()->setStatusCode(Response::HTTP_CREATED)->header('Cache-Control', 'no-store, private');
    }
}
