<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Models\Capsules\Artifact;
use App\Models\User;
use App\Queries\Capsules\LockedArtifactQuery;
use App\Support\Capsules\ArtifactLinks;
use App\Support\Capsules\VerifiedArtifactFiles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class IssueArtifactDownloadService
{
    public const SIGNED_TTL_SECONDS = 300;

    public function __construct(
        private readonly LockedArtifactQuery $targets,
        private readonly VerifiedArtifactFiles $files,
        private readonly ArtifactLinks $links,
        private readonly WriteCapsuleAudit $audit,
    ) {}

    /** @return array{artifact: Artifact, expires_at: Carbon, download_url: string, notices_url: string} */
    public function handle(User $actor, string $capsuleId, string $versionId, string $artifactId): array
    {
        return DB::transaction(function () use ($actor, $capsuleId, $versionId, $artifactId): array {
            $target = $this->targets->get($actor, $capsuleId, $versionId, $artifactId);
            $artifact = $target['artifact'];
            $this->files->kit($artifact, 'kit.zip')->close();
            $this->files->notices($artifact, 'notices.txt')->close();
            $expiresAt = Carbon::now()->utc()->addSeconds(self::SIGNED_TTL_SECONDS);
            $parameters = ['capsule' => $capsuleId, 'version' => $versionId, 'artifact' => $artifactId, 'member' => $target['actor']->id];
            // Both links must exist before an issuance can be audited or committed.
            $downloadUrl = $this->links->signed('capsules.versions.artifact.stream', $expiresAt, $parameters);
            $noticesUrl = $this->links->signed('capsules.versions.artifact.notices', $expiresAt, $parameters);
            $this->audit->capsuleVersion($target['actor'], $versionId, 'capsule.version.artifact.download_issued', [
                'artifact_id' => $artifact->id,
                'sha256' => (string) $artifact->sha256,
                'notices_sha256' => (string) $artifact->notices_sha256,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return ['artifact' => $artifact, 'expires_at' => $expiresAt, 'download_url' => $downloadUrl, 'notices_url' => $noticesUrl];
        });
    }
}
