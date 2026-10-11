<?php

namespace App\Queries\Collaboration;

use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\AccountStatus;
use App\Models\Proposal;
use App\Models\User;
use App\Queries\HelpRequests\VisibleHelpRequestsQuery;
use Illuminate\Database\Eloquent\Builder;

final class VisibleProposalsQuery
{
    public function __construct(private readonly VisibleHelpRequestsQuery $parents) {}

    /** @return Builder<Proposal> */
    public function forActor(?User $actor): Builder
    {
        return Proposal::whereNull('proposals.hidden_at')
            ->whereHas('author', fn (Builder $author) => $author->where('status', AccountStatus::Active)->whereNotNull('email_verified_at'))
            ->whereIn('request_id', $this->parents->forActor($actor)->where('state', '!=', HelpRequestState::Draft)->select('help_requests.id'));
    }

    public function get(?User $actor, string $id): Proposal
    {
        return $this->forActor($actor)->with('author:id,handle')->findOrFail(strtolower($id));
    }
}
