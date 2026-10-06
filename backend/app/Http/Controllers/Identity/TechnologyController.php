<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\Identity\TechnologyCollection;
use App\Queries\Identity\TechnologyQuery;

final class TechnologyController extends Controller
{
    public function __invoke(PaginatedRequest $request, TechnologyQuery $query): TechnologyCollection
    {
        return new TechnologyCollection($query->get($request->pageData()));
    }
}
