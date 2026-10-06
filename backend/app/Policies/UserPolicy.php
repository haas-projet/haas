<?php

namespace App\Policies;

use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\User;
use App\Support\Identity\MemberAccess;

final class UserPolicy
{
    public function __construct(private readonly MemberAccess $access) {}

    public function view(User $actor, User $user): bool
    {
        return $actor->is($user) && $actor->status === AccountStatus::Active && $user->status === AccountStatus::Active;
    }

    public function updateProfile(User $actor, User $user): bool
    {
        return $this->access->owns($actor, $user->id) && $user->status === AccountStatus::Active;
    }

    public function viewPublicProfile(?User $actor, User $user): bool
    {
        return $this->access->verified($user);
    }

    public function participate(User $actor): bool
    {
        return $this->access->verified($actor);
    }

    public function moderate(User $actor): bool
    {
        return $this->access->verified($actor) && in_array($actor->role, [Role::Moderator, Role::Admin], true);
    }

    public function administer(User $actor): bool
    {
        return $this->access->verified($actor) && $actor->role === Role::Admin;
    }

    public function manageAccountMail(User $actor, User $user): bool
    {
        return $this->view($actor, $user);
    }
}
