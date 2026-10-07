<?php

namespace App\Http\Controllers\HelpRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\HelpRequests\ListHelpRequestsRequest;
use App\Http\Resources\HelpRequests\HelpRequestCollection;
use App\Queries\HelpRequests\ListHelpRequestsQuery;

final class ListHelpRequestsController extends Controller
{
    public function __invoke(ListHelpRequestsRequest $request, ListHelpRequestsQuery $query): HelpRequestCollection
    {
        return new HelpRequestCollection($query->get($request->user(), $request->filters()));
    }
}
