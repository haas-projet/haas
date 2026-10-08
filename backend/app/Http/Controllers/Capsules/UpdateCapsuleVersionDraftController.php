<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Exceptions\Capsules\StaleCapsuleVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Capsules\UpdateCapsuleVersionDraftRequest;
use App\Http\Resources\Capsules\CapsuleVersionDraftResource;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\UpdateCapsuleVersionDraftService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateCapsuleVersionDraftController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(UpdateCapsuleVersionDraftRequest $request, UpdateCapsuleVersionDraftService $service, string $capsule, string $version): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        $target = Capsule::whereKey($capsule)->firstOrFail();
        $draft = CapsuleVersion::whereKey($version)->where('capsule_id', $target->id)->first();
        if ($draft === null) {
            // Pas de fuite d'existence : 404 identique au cas où le capsule_id/version_id ne match pas.
            throw new NotFoundHttpException;
        }
        try {
            $updated = $service->handle($actor, $target, $draft, $request->toUpdateData());
        } catch (StaleCapsuleVersion $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return (new CapsuleVersionDraftResource($updated))->response()->setStatusCode(200)->header('Cache-Control', 'no-store, private');
    }
}
