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
        if ($artifact->version_id !== $version->id) {
            return false;
        }

        // §13 : artefact actif ET notices fournies. Le sha256 est non nul
        // par contrainte de B22 ; le trigger B22+B25 fige la version publiée.
        if ($artifact->distribution_status !== ArtifactDistributionStatus::Approved) {
            return false;
        }
        if ($artifact->notices_path === null || trim((string) $artifact->notices_path) === '') {
            return false;
        }

        return true;
    }
}
