<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Data\Capsules\ArtifactSnapshot;
use App\Models\User;
use App\Queries\Capsules\LockedArtifactQuery;
use App\Support\Capsules\VerifiedArtifactFiles;
use Illuminate\Support\Facades\DB;

final class StreamArtifactService
{
    public function __construct(private readonly LockedArtifactQuery $targets, private readonly VerifiedArtifactFiles $files) {}

    public function handle(User $actor, string $capsuleId, string $versionId, string $artifactId, bool $notices = false): ArtifactSnapshot
    {
        return DB::transaction(function () use ($actor, $capsuleId, $versionId, $artifactId, $notices): ArtifactSnapshot {
            $target = $this->targets->get($actor, $capsuleId, $versionId, $artifactId);
            $name = $target['capsule']->slug.'-'.$target['version']->version_label;
            // Check both files: registry approval alone proves neither integrity nor availability.
            $kit = $this->files->kit($target['artifact'], $name.'.zip');
            try {
                $notice = $this->files->notices($target['artifact'], $name.'-notices.txt');
            } catch (\Throwable $e) {
                $kit->close();
                throw $e;
            }
            if ($notices) {
                $kit->close();

                return $notice;
            }
            $notice->close();

            return $kit;
        });
    }
}
