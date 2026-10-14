<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Support\Identity\MemberAccess;

/**
 * B28 — Enregistrer un retour humain sur une version publiée
 * (CAHIER_DES_CHARGES.md:500). Pas de retour sur un brouillon, une
 * revue, un `changes_requested` ni une version retirée.
 */
final class ReuseReportPolicy
{
    public function record(?User $actor, Capsule $capsule, CapsuleVersion $version): bool
    {
        $access = new MemberAccess;
        if (! $access->verified($actor)) {
            return false;
        }
        if ($capsule->visibility !== CapsuleVisibility::Visible) {
            return false;
        }

        return $version->state === CapsuleVersionState::Published
            && $version->capsule_id === $capsule->id;
    }
}
