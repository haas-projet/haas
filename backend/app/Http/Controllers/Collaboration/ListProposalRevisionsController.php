<?php

namespace App\Http\Controllers\Collaboration;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\Collaboration\ProposalRevisionCollection;
use App\Queries\Collaboration\ProposalQuery;

final class ListProposalRevisionsController extends Controller
{
    public function __invoke(PaginatedRequest $request, ProposalQuery $query, string $id): ProposalRevisionCollection
    {
        return new ProposalRevisionCollection($query->revisions($request->user(), $id, $request->pageData()));
    }
}
