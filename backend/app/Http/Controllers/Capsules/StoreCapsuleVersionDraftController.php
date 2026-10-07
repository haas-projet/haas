<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Http\Controllers\Controller;
use App\Http\Requests\Capsules\StoreCapsuleVersionDraftRequest;
use App\Http\Resources\Capsules\CapsuleVersionDraftResource;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\CreateCapsuleVersionDraftService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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
        $result = $service->handle(
            actor: $actor,
            capsule: $target,
            draft: $request->toDraft(),
            key: $request->idempotencyKey(),
        );
        $version = CapsuleVersion::whereKey($result['version_id'])->firstOrFail();
        $resource = new CapsuleVersionDraftResource($version);

        return $resource->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
