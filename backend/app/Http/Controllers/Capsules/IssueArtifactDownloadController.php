<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Resources\Capsules\ArtifactDownloadResource;
use App\Models\User;
use App\Services\Capsules\IssueArtifactDownloadService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IssueArtifactDownloadController extends Controller
{
    public function __invoke(Request $request, IssueArtifactDownloadService $service, string $capsule, string $version, string $artifact): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        if ($request->query() !== []) {
            throw new NotFoundHttpException;
        }
        try {
            $issued = $service->handle($actor, $capsule, $version, $artifact);
        } catch (ArtifactUnavailable) {
            throw new NotFoundHttpException;
        }

        return (new ArtifactDownloadResource($issued))->response()->header('Cache-Control', 'no-store, private');
    }
}
