<?php

namespace App\Queries\Notifications;

use App\Data\Common\PageData;
use App\Models\InternalNotification;
use App\Models\User;
use App\Queries\Collaboration\VisibleCommentsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class NotificationQuery
{
    public function __construct(private readonly VisibleCommentsQuery $comments) {}

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
        return InternalNotification::where('recipient_id', $current->id)->with('comment:id,request_id')->where(function (Builder $query) use ($current): void {
            $query->where('kind', 'profile.moderated')->orWhere(fn (Builder $comments) => $comments->where('kind', 'comment.created')->whereIn('event_id', $this->comments->forActor($current)->select('comments.id')));
        });
    }
}
