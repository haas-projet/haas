<?php

namespace App\Queries\HelpRequests;

use App\Enums\Collaboration\ProposalState;
use App\Enums\HelpRequests\HelpRequestState;
use App\Models\Resolution;
use App\Models\User;
use App\Queries\Collaboration\VisibleProposalsQuery;
use Illuminate\Database\Eloquent\Builder;

final class FindVisibleResolutionQuery
{
    public function __construct(private readonly VisibleProposalsQuery $proposals) {}

    /** @return Builder<Resolution> */
    public function forActor(?User $actor): Builder
    {
        return Resolution::whereNull('resolutions.revoked_at')
            ->whereIn('proposal_id', $this->proposals->forActor($actor)->where('state', ProposalState::Accepted)->select('proposals.id'))
            ->whereHas('request', fn (Builder $parent) => $parent->where('state', HelpRequestState::Resolved)->whereColumn('help_requests.author_id', 'resolutions.accepted_by'))
            ->whereHas('proposal', fn (Builder $proposal) => $proposal->where(function (Builder $version): void {
                $version->whereNull('resolutions.proposal_version')->orWhereColumn('proposals.lock_version', 'resolutions.proposal_version');
            }));
    }

    public function get(?User $actor, string $parentId): Resolution
    {
        return $this->forActor($actor)->with(['request', 'proposal:id,request_id,author_id,lock_version,state', 'acceptor:id,handle'])
            ->where('request_id', strtolower($parentId))->firstOrFail();
    }
}
