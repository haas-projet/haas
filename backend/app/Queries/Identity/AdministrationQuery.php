<?php

namespace App\Queries\Identity;

use App\Data\Common\PageData;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class AdministrationQuery
{
    /** @return LengthAwarePaginator<int, User> */
    public function members(User $actor, PageData $page): LengthAwarePaginator
    {
        Gate::forUser(User::findOrFail($actor->id))->authorize('administer', User::class);

        return User::orderBy('handle')->orderBy('id')->paginate($page->perPage, ['id', 'handle', 'status', 'role', 'security_version'], 'page', $page->page);
    }
}
