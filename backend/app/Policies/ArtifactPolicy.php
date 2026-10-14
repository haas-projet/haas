<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Capsules\ArtifactDistributionStatus;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Models\Capsules\Artifact;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Support\Identity\MemberAccess;

/**
 * B27 — Téléchargement d'un artefact (CAHIER_DES_CHARGES.md:956).
 *
 * §13 (:476) : le retrait d'une version désactive son téléchargement.
 * §13 (:480) et §04 ARB05 (:120) : évaluation contrôlée, droits de
 * réutilisation non déduits de la disponibilité du fichier.
 * §08 (:339) : compte non vérifié ne télécharge pas un kit contrôlé.
 *
 * Le contrôle est reproduit à chaque requête, y compris sous lien
 * signé : un retrait ultérieur coupe les anciens liens (AC15, AC25).
 */
final class ArtifactPolicy
{
    public function download(?User $actor, Capsule $capsule, CapsuleVersion $version, Artifact $artifact): bool
    {
        $access = new MemberAccess;
        if (! $access->verified($actor)) {
            return false;
        }
        if ($capsule->visibility !== CapsuleVisibility::Visible) {
            return false;
        }
        if ($version->state !== CapsuleVersionState::Published) {
            return false;
        }
        if ($version->capsule_id !== $capsule->id || $artifact->version_id !== $version->id) {
            return false;
        }

        // Approved records the server approval; availability grants no license.
        if ($artifact->distribution_status !== ArtifactDistributionStatus::Approved) {
            return false;
        }
        if ($artifact->notices_path === null || trim((string) $artifact->notices_path) === '') {
            return false;
        }
        if ($artifact->notices_sha256 === null || $artifact->notices_size === null) {
            return false;
        }

        return true;
    }
}
