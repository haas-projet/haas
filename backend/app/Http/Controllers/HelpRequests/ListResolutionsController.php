<?php

namespace App\Http\Controllers\HelpRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\HelpRequests\ResolutionHistoryCollection;
use App\Queries\HelpRequests\ResolutionHistoryQuery;

final class ListResolutionsController extends Controller
{
    public function __invoke(PaginatedRequest $request, ResolutionHistoryQuery $query, string $id): ResolutionHistoryCollection
    {
        return new ResolutionHistoryCollection($query->page($request->user(), $id, $request->pageData()));
    }
}
