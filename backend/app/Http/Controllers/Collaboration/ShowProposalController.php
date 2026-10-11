<?php

namespace App\Http\Controllers\Collaboration;

use App\Http\Controllers\Controller;
use App\Http\Resources\Collaboration\ProposalResource;
use App\Queries\Collaboration\VisibleProposalsQuery;
use Illuminate\Http\Request;

final class ShowProposalController extends Controller
{
    public function __invoke(Request $request, VisibleProposalsQuery $query, string $id): ProposalResource
    {
        return new ProposalResource($query->get($request->user(), $id));
    }
}
