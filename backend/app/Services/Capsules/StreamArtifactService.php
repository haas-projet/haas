<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Policies\ArtifactPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

/**
 * B27 — Valide l'accès à l'artefact et renvoie les éléments de
 * livraison (disque local, chemin privé déjà vérifié, nom public).
 * Le Controller construit la réponse HTTP ; le Service reste couche
 * métier (`DomainBoundariesTest`).
 */
final class StreamArtifactService
{
    public function __construct(private readonly ArtifactPolicy $policy) {}

    /**
     * @return array{disk: string, path: string, download_name: string}
     */
    public function handle(User $actor, Capsule $capsule, CapsuleVersion $version, Artifact $artifact): array
    {
        if (! $this->policy->download($actor, $capsule, $version, $artifact)) {
            throw new AuthorizationException;
        }
        $privatePath = (string) $artifact->getAttribute('private_path');
        if (! $this->looksLikeSafePath($privatePath)) {
            throw new ArtifactUnavailable;
        }
        if (! Storage::disk('local')->exists($privatePath)) {
            throw new ArtifactUnavailable;
        }

        return [
            'disk' => 'local',
            'path' => $privatePath,
            'download_name' => $this->downloadName($capsule, $version, $privatePath),
        ];
    }

    private function looksLikeSafePath(string $path): bool
    {
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        return preg_match('#^(?:[a-zA-Z]:|/|\\\\)#', $path) !== 1 && preg_match('#[\x00-\x1F]#u', $path) !== 1;
    }

    private function downloadName(Capsule $capsule, CapsuleVersion $version, string $privatePath): string
    {
        $extension = pathinfo($privatePath, PATHINFO_EXTENSION);
        $base = $capsule->slug.'-'.$version->version_label;

        return $extension === '' ? $base : $base.'.'.$extension;
    }
}
