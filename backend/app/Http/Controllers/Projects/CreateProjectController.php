<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\CreateProjectRequest;
use App\Http\Resources\Projects\ProjectResource;
use App\Services\Projects\CreateProjectService;
use Illuminate\Http\JsonResponse;

final class CreateProjectController extends Controller
{
    public function __invoke(CreateProjectRequest $request, CreateProjectService $service): JsonResponse
    {
        return (new ProjectResource($service->create($request->member(), $request->projectData(), $request->idempotencyKey())))
            ->response()->setStatusCode(201);
    }
}
