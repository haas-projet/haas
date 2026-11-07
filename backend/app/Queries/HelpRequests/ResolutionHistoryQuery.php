<?php

namespace App\Queries\HelpRequests;

use App\Data\Common\PageData;
use App\Enums\Collaboration\ProposalState;
use App\Enums\Identity\AccountStatus;
use App\Models\Resolution;
use App\Models\User;
use App\Queries\Collaboration\VisibleProposalsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ResolutionHistoryQuery
{
    public function __construct(private readonly FindVisibleHelpRequestQuery $parents, private readonly VisibleProposalsQuery $proposals) {}

    /** @return Builder<Resolution> */
    public function forActor(?User $actor): Builder
    {
        return Resolution::whereIn('proposal_id', $this->proposals->forActor($actor)->where('state', ProposalState::Accepted)->select('proposals.id'))
            ->whereHas('acceptor', fn (Builder $acceptor) => $acceptor->where('status', AccountStatus::Active)->whereNotNull('email_verified_at'))
            ->whereHas('request', fn (Builder $parent) => $parent->whereColumn('help_requests.author_id', 'resolutions.accepted_by'))
            ->whereHas('proposal', fn (Builder $proposal) => $proposal->whereColumn('proposals.request_id', 'resolutions.request_id')
                ->where(function (Builder $version): void {
                    $version->whereNull('resolutions.proposal_version')->orWhereColumn('proposals.lock_version', 'resolutions.proposal_version');
                }))
            ->where(function (Builder $history): void {
                $history->whereDoesntHave('reopening')->orWhereHas('reopening', fn (Builder $journal) => $journal
                    ->whereColumn('request_reopenings.request_id', 'resolutions.request_id')
                    ->whereColumn('request_reopenings.reopened_by', 'resolutions.accepted_by')
                    ->whereColumn('request_reopenings.reopened_at', 'resolutions.revoked_at'));
            });
    }

    /** @return LengthAwarePaginator<int, Resolution> */
    public function page(?User $actor, string $parentId, PageData $page): LengthAwarePaginator
    {
        $parent = $this->parents->get($actor, strtolower($parentId));

        return $this->forActor($actor)->where('request_id', $parent->id)
            ->with(['request', 'proposal:id,request_id,author_id', 'acceptor:id,handle', 'reopening.author:id,handle'])
            ->orderByDesc('accepted_at')->orderByDesc('id')->paginate($page->perPage, ['resolutions.*'], 'page', $page->page);
    }
}
