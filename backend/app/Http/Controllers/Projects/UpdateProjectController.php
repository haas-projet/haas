<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\Projects\ProjectResource;
use App\Services\Projects\UpdateProjectService;

final class UpdateProjectController extends Controller
{
    public function __invoke(UpdateProjectRequest $request, UpdateProjectService $service): ProjectResource
    {
        return new ProjectResource($service->update($request->member(), $request->projectId(), $request->projectData(), $request->idempotencyKey()));
    }
}
