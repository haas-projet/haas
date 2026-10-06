<?php

namespace App\Queries\Identity;

use App\Enums\Identity\AccountStatus;
use App\Exceptions\Identity\InactiveAccount;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class ProfileQuery
{
    public function publicProfile(string $handle): User
    {
        $user = $this->query()->where('status', AccountStatus::Active)->whereNotNull('email_verified_at')
            ->whereDoesntHave('profile', fn ($query) => $query->whereNotNull('hidden_at'))
            ->whereRaw('lower(handle) = lower(?)', [$handle])->firstOrFail();
        Gate::authorize('viewPublicProfile', $user);

        return $user;
    }

    public function own(User $actor): User
    {
        $user = $this->query()->find($actor->id);
        if ($user === null) {
            throw new AuthenticationException;
        }
        if ($user->status !== AccountStatus::Active) {
            throw new InactiveAccount;
        }
        Gate::forUser($user)->authorize('view', $user);

        return $user;
    }

    /** @return Builder<User> */
    private function query(): Builder
    {
        return User::query()->select(['id', 'handle', 'role', 'status', 'email_verified_at', 'is_demo'])
            ->with(['profile', 'technologies' => fn ($query) => $query->orderBy('slug')->orderBy('technologies.id')]);
    }
}
