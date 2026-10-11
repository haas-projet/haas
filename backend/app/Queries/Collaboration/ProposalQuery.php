<?php

namespace App\Queries\Collaboration;

use App\Data\Common\PageData;
use App\Models\Proposal;
use App\Models\ProposalRevision;
use App\Models\User;
use App\Queries\HelpRequests\FindVisibleHelpRequestQuery;
use Illuminate\Pagination\LengthAwarePaginator;

final class ProposalQuery
{
    public function __construct(private readonly VisibleProposalsQuery $visible, private readonly FindVisibleHelpRequestQuery $parents) {}

    /** @return LengthAwarePaginator<int, Proposal> */
    public function page(?User $actor, string $id, PageData $page): LengthAwarePaginator
    {
        $parent = $this->parents->get($actor, strtolower($id));

        return $this->visible->forActor($actor)->where('request_id', $parent->id)->with('author:id,handle')->orderBy('created_at')->orderBy('id')->paginate($page->perPage, ['*'], 'page', $page->page);
    }

    /** @return LengthAwarePaginator<int, ProposalRevision> */
    public function revisions(?User $actor, string $id, PageData $page): LengthAwarePaginator
    {
        $proposal = $this->visible->get($actor, $id);

        return ProposalRevision::where('proposal_id', $proposal->id)->orderByDesc('proposal_version')->paginate($page->perPage, ['*'], 'page', $page->page);
    }
}
