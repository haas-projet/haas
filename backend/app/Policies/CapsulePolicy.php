<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
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
 * - update(draft/changes_requested) : owner ou contributeur de cette version.
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

    /**
     * La résolution active est relue en base à chaque contrôle.
     *
     * @phpstan-impure
     */
    public function proposeFromHelpRequest(?User $actor, HelpRequest $request): bool
    {
        $access = new MemberAccess;
        if ($request->hidden_at !== null || ! $access->verified($actor) || ! $access->verified($request->author)) {
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
        return $capsule->visibility === CapsuleVisibility::Visible && $capsule->owner_id === $actor->id;
    }

    public function editDraft(?User $actor, Capsule $capsule, CapsuleVersionState $state, string $versionId): bool
    {
        if ($capsule->visibility !== CapsuleVisibility::Visible || ($state !== CapsuleVersionState::Draft && $state !== CapsuleVersionState::ChangesRequested)) {
            return false;
        }
        $access = new MemberAccess;
        if (! $access->verified($actor)) {
            return false;
        }

        /** @var User $actor */
        if ($capsule->owner_id === $actor->id) {
            return true;
        }

        // Contributeur de cette version-ci (owner d'origine ou co-contributeur habilité).
        return DB::table('capsule_contributors')
            ->where('version_id', $versionId)
            ->where('user_id', $actor->id)
            ->exists();
    }

    /** Owner ou contributeur de la version peut soumettre à la revue (draft ou changes_requested). */
    public function submitForReview(?User $actor, Capsule $capsule, CapsuleVersionState $state, string $versionId): bool
    {
        if ($state !== CapsuleVersionState::Draft && $state !== CapsuleVersionState::ChangesRequested) {
            return false;
        }

        return $this->editDraft($actor, $capsule, $state, $versionId);
    }

    /**
     * Moderator/admin qui n'est NI owner NI contributeur de la version peut la relire.
     * CAHIER_DES_CHARGES.md:462 : « L'auteur ne valide pas seul sa propre revue
     * éditoriale. Un administrateur conserve cette séparation même s'il détient tous
     * les droits techniques. »
     */
    public function reviewVersion(?User $actor, Capsule $capsule, string $versionId): bool
    {
        if ($capsule->visibility !== CapsuleVisibility::Visible) {
            return false;
        }
        $access = new MemberAccess;
        if (! $access->verified($actor)) {
            return false;
        }

        /** @var User $actor */
        if ($actor->role !== Role::Moderator && $actor->role !== Role::Admin) {
            return false;
        }
        if ($capsule->owner_id === $actor->id) {
            return false;
        }

        return ! DB::table('capsule_contributors')
            ->where('version_id', $versionId)
            ->where('user_id', $actor->id)
            ->exists();
    }

    /**
     * Publier une version : mêmes exigences que la revue indépendante
     * (B24 + AC12). CAHIER_DES_CHARGES.md:462 interdit à l'auteur de valider
     * sa propre revue éditoriale, étend cette séparation aux administrateurs ;
     * §955 ajoute « Réviseur distinct ; contrôle de droits et contenu ».
     */
    public function publishVersion(?User $actor, Capsule $capsule, string $versionId): bool
    {
        return $this->reviewVersion($actor, $capsule, $versionId);
    }
}
