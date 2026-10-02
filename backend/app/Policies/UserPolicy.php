<?php

namespace App\Policies;

use App\Enums\Identity\AccountStatus;
use App\Models\User;

final class UserPolicy
{
    public function manageAccountMail(User $actor, User $user): bool
    {
        return $actor->is($user) && $user->status === AccountStatus::Active;
    }
}
