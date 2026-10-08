<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Http\Controllers\Controller;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\StreamArtifactService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StreamArtifactDownloadController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(Request $request, StreamArtifactService $service, string $capsule, string $version, string $artifact): StreamedResponse
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
            $delivery = $service->handle($actor, $target, $draft, $kit);
        } catch (ArtifactUnavailable|AuthorizationException) {
            throw new NotFoundHttpException;
        }

        return Storage::disk($delivery['disk'])->download($delivery['path'], $delivery['download_name'], [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
