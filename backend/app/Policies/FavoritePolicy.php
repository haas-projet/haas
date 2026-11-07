<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Capsules\CapsuleVisibility;
use App\Models\Capsules\Capsule;
use App\Models\User;
use App\Support\Identity\MemberAccess;

/**
 * B28 — Un favori est un lien privé entre un membre vérifié/actif et
 * une capsule visible. Aucune route tierce ni Resource publique ne le
 * révèle (CAHIER_DES_CHARGES.md:506).
 */
final class FavoritePolicy
{
    public function mark(?User $actor, Capsule $capsule): bool
    {
        $access = new MemberAccess;
        if (! $access->verified($actor)) {
            return false;
        }

        return $capsule->visibility === CapsuleVisibility::Visible;
    }

    public function unmark(?User $actor, Capsule $capsule): bool
    {
        $access = new MemberAccess;

        return $access->verified($actor);
    }
}
