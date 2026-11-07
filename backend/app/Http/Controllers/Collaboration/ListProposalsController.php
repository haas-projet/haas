<?php

namespace App\Http\Controllers\Collaboration;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\Collaboration\ProposalCollection;
use App\Queries\Collaboration\ProposalQuery;

final class ListProposalsController extends Controller
{
    public function __invoke(PaginatedRequest $request, ProposalQuery $query, string $id): ProposalCollection
    {
        return new ProposalCollection($query->page($request->user(), $id, $request->pageData()));
    }
}
