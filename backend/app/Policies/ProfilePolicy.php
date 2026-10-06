<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;
use App\Support\Identity\MemberAccess;

final class ProfilePolicy
{
    public function report(User $actor, Profile $profile): bool
    {
        $access = new MemberAccess;

        return $access->verified($actor) && $profile->hidden_at === null && $profile->user !== null && $access->verified($profile->user);
    }
}
