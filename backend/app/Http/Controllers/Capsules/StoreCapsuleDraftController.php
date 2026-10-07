<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Http\Controllers\Controller;
use App\Http\Requests\Capsules\StoreCapsuleDraftRequest;
use App\Http\Resources\Capsules\CapsuleDraftResource;
use App\Models\Capsules\Capsule;
use App\Models\User;
use App\Services\Capsules\CreateCapsuleDraftService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class StoreCapsuleDraftController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(StoreCapsuleDraftRequest $request, CreateCapsuleDraftService $service): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        $result = $service->handle(
            actor: $actor,
            data: $request->toCapsuleDraft(),
            key: $request->idempotencyKey(),
        );
        $capsule = Capsule::whereKey($result['capsule_id'])->firstOrFail();
        $resource = new CapsuleDraftResource($capsule);

        return $resource->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
