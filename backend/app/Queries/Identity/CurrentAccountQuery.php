<?php

namespace App\Queries\Identity;

use App\Enums\Identity\AccountStatus;
use App\Exceptions\Identity\InactiveAccount;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Gate;

final class CurrentAccountQuery
{
    public function get(User $actor): User
    {
        $current = User::query()->select(['id', 'handle', 'email', 'email_verified_at', 'role', 'status', 'is_demo'])->find($actor->id);
        if ($current === null) {
            throw new AuthenticationException;
        }
        if ($current->status !== AccountStatus::Active) {
            throw new InactiveAccount;
        }
        Gate::forUser($current)->authorize('view', $current);

        return $current;
    }
}
