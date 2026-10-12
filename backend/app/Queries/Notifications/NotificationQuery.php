<?php

namespace App\Queries\Notifications;

use App\Data\Common\PageData;
use App\Models\InternalNotification;
use App\Models\User;
use App\Queries\Collaboration\VisibleCommentsQuery;
use App\Queries\Collaboration\VisibleProposalsQuery;
use App\Queries\HelpRequests\FindVisibleResolutionQuery;
use App\Queries\HelpRequests\VisibleRequestReopeningsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class NotificationQuery
{
    public function __construct(private readonly VisibleCommentsQuery $comments, private readonly VisibleProposalsQuery $proposals,
        private readonly FindVisibleResolutionQuery $resolutions, private readonly VisibleRequestReopeningsQuery $reopenings) {}

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
        return InternalNotification::where('recipient_id', $current->id)->with(['comment:id,request_id', 'proposal:id,request_id', 'resolution:id,request_id', 'reopening:id,request_id'])->where(function (Builder $query) use ($current): void {
            $query->where('kind', 'profile.moderated')->orWhere(fn (Builder $comments) => $comments->where('kind', 'comment.created')->whereIn('event_id', $this->comments->forActor($current)->select('comments.id')));
            $query->orWhere(fn (Builder $proposals) => $proposals->where('kind', 'proposal.created')
                ->whereIn('event_id', $this->proposals->forActor($current)->whereHas('request', fn (Builder $parent) => $parent->where('author_id', $current->id))->where('author_id', '!=', $current->id)->select('proposals.id')));
            $query->orWhere(fn (Builder $resolutions) => $resolutions->where('kind', 'resolution.accepted')
                ->whereIn('event_id', $this->resolutions->forActor($current)->where('accepted_by', '!=', $current->id)
                    ->whereHas('proposal', fn (Builder $proposal) => $proposal->where('author_id', $current->id))->select('resolutions.id')));
            $query->orWhere(fn (Builder $reopenings) => $reopenings->where('kind', 'request.reopened')
                ->whereIn('event_id', $this->reopenings->forActor($current)->where('reopened_by', '!=', $current->id)
                    ->whereHas('resolution.proposal', fn (Builder $proposal) => $proposal->where('author_id', $current->id))->select('request_reopenings.id')));
        });
    }
}
