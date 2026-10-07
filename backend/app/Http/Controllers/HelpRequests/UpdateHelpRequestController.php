<?php

namespace App\Http\Controllers\HelpRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\HelpRequests\UpdateHelpRequestRequest;
use App\Http\Resources\HelpRequests\HelpRequestResource;
use App\Services\HelpRequests\UpdateHelpRequestService;

final class UpdateHelpRequestController extends Controller
{
    public function __invoke(UpdateHelpRequestRequest $request, UpdateHelpRequestService $service): HelpRequestResource
    {
        return new HelpRequestResource($service->update($request->member(), $request->requestId(), $request->requestData(), $request->idempotencyKey()));
    }
}
