<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ReadAccountRequest;
use App\Http\Resources\Identity\PublicProfileResource;
use App\Queries\Identity\ProfileQuery;

final class PublicProfileController extends Controller
{
    public function __invoke(ReadAccountRequest $request, ProfileQuery $query, string $handle): PublicProfileResource
    {
        return new PublicProfileResource($query->publicProfile($handle));
    }
}
