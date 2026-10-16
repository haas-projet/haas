<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Capsules\StreamArtifactService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StreamArtifactDownloadController extends Controller
{
    public function __invoke(Request $request, StreamArtifactService $service, string $capsule, string $version, string $artifact): StreamedResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        if ($request->query('member') !== $actor->id
            || array_diff(array_keys($request->query()), ['member', 'expires', 'signature']) !== []) {
            throw new NotFoundHttpException;
        }
        try {
            $delivery = $service->handle($actor, $capsule, $version, $artifact,
                $request->route()?->getName() === 'capsules.versions.artifact.notices');
        } catch (ArtifactUnavailable) {
            throw new NotFoundHttpException;
        }

        return response()->streamDownload(fn () => $delivery->send(), $delivery->downloadName, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) $delivery->size,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
