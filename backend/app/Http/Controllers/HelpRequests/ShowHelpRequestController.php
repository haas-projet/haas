<?php

namespace App\Http\Controllers\HelpRequests;

use App\Http\Controllers\Controller;
use App\Http\Resources\HelpRequests\HelpRequestResource;
use App\Queries\HelpRequests\FindVisibleHelpRequestQuery;
use Illuminate\Http\Request;

final class ShowHelpRequestController extends Controller
{
    public function __invoke(Request $request, string $id, FindVisibleHelpRequestQuery $query): HelpRequestResource
    {
        return new HelpRequestResource($query->get($request->user(), $id));
    }
}
