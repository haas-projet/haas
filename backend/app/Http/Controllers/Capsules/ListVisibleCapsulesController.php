<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Http\Controllers\Controller;
use App\Http\Requests\Capsules\ListCapsulesCatalogueRequest;
use App\Http\Resources\Capsules\CapsuleCataloguePage;
use App\Queries\Capsules\ListVisibleCapsulesQuery;

final class ListVisibleCapsulesController extends Controller
{
    public function __invoke(ListCapsulesCatalogueRequest $request, ListVisibleCapsulesQuery $query): CapsuleCataloguePage
    {
        return new CapsuleCataloguePage($query->get($request->filters()));
    }
}
