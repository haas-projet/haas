<?php

namespace App\Http\Controllers\HelpRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\HelpRequests\ReopenHelpRequestRequest;
use App\Http\Resources\HelpRequests\RequestReopeningResource;
use App\Services\HelpRequests\ReopenHelpRequestService;

final class ReopenHelpRequestController extends Controller
{
    public function __invoke(ReopenHelpRequestRequest $request, ReopenHelpRequestService $service): RequestReopeningResource
    {
        return new RequestReopeningResource($service->reopen($request->member(), $request->requestId(), $request->reopeningData(), $request->idempotencyKey()));
    }
}
