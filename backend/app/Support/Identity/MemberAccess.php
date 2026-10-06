<?php

namespace App\Support\Identity;

use App\Enums\Identity\AccountStatus;
use App\Models\User;

final class MemberAccess
{
    public function verified(?User $actor): bool
    {
        return $actor !== null && $actor->status === AccountStatus::Active && $actor->hasVerifiedEmail();
    }

    // Le propriétaire provient de la ressource relue par le serveur, jamais des entrées HTTP.
    public function owns(?User $actor, string $ownerId): bool
    {
        return $actor !== null && $this->verified($actor) && $actor->id === $ownerId;
    }
}
