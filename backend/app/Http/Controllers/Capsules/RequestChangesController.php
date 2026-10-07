<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Http\Controllers\Controller;
use App\Http\Requests\Capsules\RequestChangesRequest;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\RequestChangesOnCapsuleVersionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
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
        $review = $service->handle($actor, $target, $draft, $request->note());

        return new JsonResponse([
            'data' => [
                'id' => $review->id,
                'version_id' => $review->version_id,
                'decision' => $review->decision->value,
                'note' => $review->note,
                'created_at' => $review->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
