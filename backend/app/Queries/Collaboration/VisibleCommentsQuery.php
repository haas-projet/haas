<?php

namespace App\Queries\Collaboration;

use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\AccountStatus;
use App\Models\Comment;
use App\Models\User;
use App\Queries\HelpRequests\VisibleHelpRequestsQuery;
use Illuminate\Database\Eloquent\Builder;

final class VisibleCommentsQuery
{
    public function __construct(private readonly VisibleHelpRequestsQuery $parents) {}

    /** @return Builder<Comment> */
    public function forActor(?User $actor): Builder
    {
        return Comment::whereNull('comments.hidden_at')
            ->whereHas('author', fn (Builder $author) => $author->where('status', AccountStatus::Active)->whereNotNull('email_verified_at'))
            ->whereIn('request_id', $this->parents->forActor($actor)->where('state', '!=', HelpRequestState::Draft)->select('help_requests.id'));
    }

    public function get(?User $actor, string $id): Comment
    {
        return $this->forActor($actor)->with('author:id,handle')->findOrFail(strtolower($id));
    }
}
