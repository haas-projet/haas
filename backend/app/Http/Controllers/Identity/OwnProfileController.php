<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\MeRequest;
use App\Http\Resources\Identity\OwnProfileResource;
use App\Queries\Identity\ProfileQuery;

final class OwnProfileController extends Controller
{
    public function __invoke(MeRequest $request, ProfileQuery $query): OwnProfileResource
    {
        return new OwnProfileResource($query->own($request->member()));
    }
}
