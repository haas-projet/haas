<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\UpdateProfileRequest;
use App\Http\Resources\Identity\OwnProfileResource;
use App\Services\Identity\UpdateProfileService;

final class UpdateProfileController extends Controller
{
    public function __invoke(UpdateProfileRequest $request, UpdateProfileService $service): OwnProfileResource
    {
        return new OwnProfileResource($service->update($request->member(), $request->profileData()));
    }
}
