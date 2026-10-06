<?php

namespace App\Queries\Notifications;

use App\Data\Common\PageData;
use App\Models\InternalNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class NotificationQuery
{
    /** @return LengthAwarePaginator<int, InternalNotification> */
    public function page(User $actor, PageData $page): LengthAwarePaginator
    {
        return $this->visible($actor)->orderByDesc('created_at')->orderByDesc('id')->paginate($page->perPage, ['*'], 'page', $page->page);
    }

    public function unread(User $actor): int
    {
        return $this->visible($actor)->whereNull('read_at')->count();
    }

    /** @return Builder<InternalNotification> */
    public function visible(User $actor): Builder
    {
        $current = User::findOrFail($actor->id);
        Gate::forUser($current)->authorize('view', $current);

        // Une notification de modération ne copie aucun contenu ni lien public retiré.
        return InternalNotification::where('recipient_id', $current->id)->where('kind', 'profile.moderated');
    }
}
