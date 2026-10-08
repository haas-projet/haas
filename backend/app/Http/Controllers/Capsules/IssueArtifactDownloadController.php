<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Resources\Capsules\ArtifactDownloadResource;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\IssueArtifactDownloadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IssueArtifactDownloadController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(Request $request, IssueArtifactDownloadService $service, string $capsule, string $version, string $artifact): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        $target = Capsule::whereKey($capsule)->firstOrFail();
        $draft = CapsuleVersion::whereKey($version)->where('capsule_id', $target->id)->first();
        $kit = $draft === null ? null : Artifact::whereKey($artifact)->where('version_id', $draft->id)->first();
        if ($draft === null || $kit === null) {
            throw new NotFoundHttpException;
        }
        try {
            $issued = $service->handle($actor, $target, $draft, $kit);
        } catch (ArtifactUnavailable|AuthorizationException) {
            throw new NotFoundHttpException;
        }
        $downloadUrl = URL::temporarySignedRoute(
            'capsules.versions.artifact.stream',
            $issued['expires_at'],
            ['capsule' => $target->id, 'version' => $draft->id, 'artifact' => $kit->id],
        );
        $payload = ['artifact' => $issued['artifact'], 'download_url' => $downloadUrl, 'expires_at' => $issued['expires_at']];

        return (new ArtifactDownloadResource($payload))->response()->setStatusCode(200)->header('Cache-Control', 'no-store, private');
    }
}
