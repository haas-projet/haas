<?php

namespace App\Http\Controllers\HelpRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\HelpRequests\HelpRequestRevisionCollection;
use App\Queries\HelpRequests\ListHelpRequestRevisionsQuery;

final class ListHelpRequestRevisionsController extends Controller
{
    public function __invoke(PaginatedRequest $request, string $id, ListHelpRequestRevisionsQuery $query): HelpRequestRevisionCollection
    {
        return new HelpRequestRevisionCollection($query->get($request->user(), $id, $request->pageData()));
    }
}
