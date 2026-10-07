<?php

namespace App\Http\Controllers\HelpRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\HelpRequests\StoreHelpRequestRequest;
use App\Http\Resources\HelpRequests\HelpRequestResource;
use App\Services\HelpRequests\CreateHelpRequestService;
use Illuminate\Http\JsonResponse;

final class CreateHelpRequestController extends Controller
{
    public function __invoke(StoreHelpRequestRequest $request, CreateHelpRequestService $service): JsonResponse
    {
        return (new HelpRequestResource($service->create($request->member(), $request->requestData(), $request->idempotencyKey())))
            ->response()->setStatusCode(201);
    }
}
