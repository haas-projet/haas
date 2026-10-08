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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * B27 — Autorise un téléchargement d'artefact et renvoie les éléments
 * de confiance (artefact validé, échéance). Le Controller assemble
 * ensuite l'URL signée via l'infrastructure HTTP ; le Service reste
 * dans la couche métier (`DomainBoundariesTest`).
 */
final class IssueArtifactDownloadService
{
    /** Durée du lien signé (secondes) — court, pour qu'un retrait coupe les anciens. */
    public const SIGNED_TTL_SECONDS = 300;

    public function __construct(
        private readonly ArtifactPolicy $policy,
        private readonly WriteCapsuleAudit $audit,
    ) {}

    /**
     * @return array{artifact: Artifact, expires_at: Carbon}
     */
    public function handle(User $actor, Capsule $capsule, CapsuleVersion $version, Artifact $artifact): array
    {
        if (! $this->policy->download($actor, $capsule, $version, $artifact)) {
            throw new AuthorizationException;
        }
        if (! $this->looksLikeSafePath($artifact->getAttribute('private_path'))) {
            throw new ArtifactUnavailable;
        }

        $expiresAt = Carbon::now()->utc()->addSeconds(self::SIGNED_TTL_SECONDS);
        DB::transaction(function () use ($actor, $version, $artifact, $expiresAt): void {
            // Audit de l'intention : digest et échéance publics, jamais
            // de chemin disque ni d'URL signée brute.
            $this->audit->capsuleVersion($actor, $version->id, 'capsule.version.artifact.download_issued', [
                'artifact_id' => $artifact->id,
                'sha256' => (string) $artifact->getAttribute('sha256'),
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
        });

        return ['artifact' => $artifact, 'expires_at' => $expiresAt];
    }

    private function looksLikeSafePath(mixed $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }
        if (str_contains($path, '..') || preg_match('#^(?:[a-zA-Z]:|/|\\\\)#', $path) === 1) {
            return false;
        }

        return preg_match('#[\x00-\x1F]#u', $path) !== 1;
    }
}
