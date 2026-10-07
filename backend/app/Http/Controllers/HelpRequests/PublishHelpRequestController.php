<?php

namespace App\Http\Controllers\HelpRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\HelpRequests\ChangeHelpRequestRequest;
use App\Http\Resources\HelpRequests\HelpRequestResource;
use App\Services\HelpRequests\UpdateHelpRequestService;

final class PublishHelpRequestController extends Controller
{
    public function __invoke(ChangeHelpRequestRequest $request, UpdateHelpRequestService $service): HelpRequestResource
    {
        return new HelpRequestResource($service->publish($request->member(), $request->requestId(), $request->version(), $request->idempotencyKey()));
    }
}
