<?php

declare(strict_types=1);

namespace App\Queries\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base de la visibilité publique des capsules (B26, RM06).
 *
 * Une capsule n'apparaît que si :
 *  - `visibility = visible` ;
 *  - au moins une version `published` existe actuellement.
 *
 * Les versions `draft`, `in_review`, `changes_requested` et `withdrawn`
 * n'apparaissent dans aucune liste, recherche, détail ou historique.
 */
final class VisibleCapsulesQuery
{
    /** @return Builder<Capsule> */
    public function builder(): Builder
    {
        return Capsule::query()
            ->where('visibility', CapsuleVisibility::Visible)
            ->whereHas('versions', fn (Builder $query) => $query->where('state', CapsuleVersionState::Published));
    }

    /** @return Builder<CapsuleVersion> */
    public function publishedVersions(string $capsuleId): Builder
    {
        return CapsuleVersion::query()
            ->where('capsule_id', $capsuleId)
            ->where('state', CapsuleVersionState::Published);
    }
}
