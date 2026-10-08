<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Exceptions\Capsules\InsufficientDraftContent;
use App\Exceptions\Capsules\StaleCapsuleVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Capsules\PublishCapsuleVersionRequest;
use App\Http\Resources\Capsules\CapsuleVersionPublishedResource;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\PublishCapsuleVersionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublishCapsuleVersionController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(PublishCapsuleVersionRequest $request, PublishCapsuleVersionService $service, string $capsule, string $version): JsonResponse
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
            $published = $service->handle($actor, $target, $draft, $request->command(), $request->idempotencyKey());
        } catch (InsufficientDraftContent $e) {
            throw ValidationException::withMessages([
                'content' => explode('|', $e->getMessage()),
            ]);
        } catch (StaleCapsuleVersion $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        }

        return (new CapsuleVersionPublishedResource($published))->response()->setStatusCode(200)->header('Cache-Control', 'no-store, private');
    }
}
