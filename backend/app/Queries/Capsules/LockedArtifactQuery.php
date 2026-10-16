<?php

declare(strict_types=1);

namespace App\Queries\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Policies\ArtifactPolicy;
use Illuminate\Support\Facades\DB;
use LogicException;

final class LockedArtifactQuery
{
    public function __construct(private readonly ArtifactPolicy $policy) {}

    /** @return array{actor: User, capsule: Capsule, version: CapsuleVersion, artifact: Artifact} */
    public function get(User $actor, string $capsuleId, string $versionId, string $artifactId): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || DB::transactionLevel() < 1) {
            throw new LogicException('Lecture du kit requise dans sa transaction PostgreSQL.');
        }
        // Same actor -> capsule -> version order as capsule commands. The version
        // lock also serializes MAX(revision)+1 in the transactional audit.
        $currentActor = User::query()->whereKey($actor->id)->lock('FOR SHARE')->first();
        $capsule = Capsule::query()->whereKey($capsuleId)->lockForUpdate()->first();
        $version = CapsuleVersion::query()->whereKey($versionId)->where('capsule_id', $capsuleId)->lockForUpdate()->first();
        // The B27 trigger locks this version for every artifact write. A normal
        // MVCC read avoids a cycle with SQL UPDATE, whose tuple is locked before
        // that trigger waits for our version lock. Writes committed before this
        // gate are visible; later revocations wait until this authorization ends.
        $artifact = Artifact::query()->whereKey($artifactId)->where('version_id', $versionId)->first();
        if ($currentActor === null || $capsule === null || $version === null || $artifact === null
            || ! $this->policy->download($currentActor, $capsule, $version, $artifact)) {
            throw new ArtifactUnavailable;
        }

        return ['actor' => $currentActor, 'capsule' => $capsule, 'version' => $version, 'artifact' => $artifact];
    }
}
