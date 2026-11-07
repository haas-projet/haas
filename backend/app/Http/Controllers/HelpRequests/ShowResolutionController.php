<?php

namespace App\Http\Controllers\HelpRequests;

use App\Http\Controllers\Controller;
use App\Http\Resources\HelpRequests\ResolutionResource;
use App\Queries\HelpRequests\FindVisibleResolutionQuery;
use Illuminate\Http\Request;

final class ShowResolutionController extends Controller
{
    public function __invoke(Request $request, FindVisibleResolutionQuery $query, string $id): ResolutionResource
    {
        return new ResolutionResource($query->get($request->user(), $id));
    }
}
