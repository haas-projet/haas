<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Identity\Role;
use App\Models\Capsules\Capsule;
use App\Models\HelpRequest;
use App\Models\User;
use App\Support\Identity\MemberAccess;
use Illuminate\Support\Facades\DB;

/**
 * Policies des brouillons de capsule (lot B23).
 *
 * - propose(editorial) : moderator/admin uniquement (CAHIER_DES_CHARGES.md:220 et :456 ;
 *   « origine éditoriale clairement signalée pour les briques de démonstration »).
 * - propose(help_request) : l'auteur de la demande résolue OU l'auteur de la proposition
 *   acceptée par la résolution active, décision Q9 fondée sur CAHIER_DES_CHARGES.md:265
 *   (« L'auteur d'une résolution ou un contributeur autorisé propose une capsule »).
 * - update(draft) : owner de la capsule uniquement en B23. La délégation à un
 *   contributeur habilité attend B24/B25.
 */
final class CapsulePolicy
{
    public function proposeEditorial(?User $actor): bool
    {
        $access = new MemberAccess;
        if (! $access->verified($actor)) {
            return false;
        }

        /** @var User $actor */
        return $actor->role === Role::Moderator || $actor->role === Role::Admin;
    }

    public function proposeFromHelpRequest(?User $actor, HelpRequest $request): bool
    {
        $access = new MemberAccess;
        if (! $access->verified($actor)) {
            return false;
        }

        /** @var User $actor */
        if ($request->author_id === $actor->id) {
            return true;
        }

        // Décision Q9 (CAHIER_DES_CHARGES.md:265) : l'auteur de la proposition acceptée
        // (résolution active, revoked_at nul) peut aussi proposer la capsule.
        return DB::table('resolutions')
            ->join('proposals', 'resolutions.proposal_id', '=', 'proposals.id')
            ->where('resolutions.request_id', $request->id)
            ->whereNull('resolutions.revoked_at')
            ->where('proposals.author_id', $actor->id)
            ->exists();
    }

    public function createVersionDraft(?User $actor, Capsule $capsule): bool
    {
        $access = new MemberAccess;
        if (! $access->verified($actor)) {
            return false;
        }

        /** @var User $actor */
        return $capsule->owner_id === $actor->id;
    }

    public function editDraft(?User $actor, Capsule $capsule, CapsuleVersionState $state): bool
    {
        if ($state !== CapsuleVersionState::Draft) {
            return false;
        }

        return $this->createVersionDraft($actor, $capsule);
    }
}
