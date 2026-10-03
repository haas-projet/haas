<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\MeRequest;
use App\Http\Resources\Identity\MeResource;
use App\Queries\Identity\CurrentAccountQuery;

final class MeController extends Controller
{
    public function __invoke(MeRequest $request, CurrentAccountQuery $query): MeResource
    {
        return new MeResource($query->get($request->member()));
    }
}
