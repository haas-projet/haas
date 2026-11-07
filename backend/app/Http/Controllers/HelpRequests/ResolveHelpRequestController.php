<?php

namespace App\Http\Controllers\HelpRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\HelpRequests\ResolveHelpRequestRequest;
use App\Http\Resources\HelpRequests\ResolutionResource;
use App\Services\HelpRequests\ResolveHelpRequestService;

final class ResolveHelpRequestController extends Controller
{
    public function __invoke(ResolveHelpRequestRequest $request, ResolveHelpRequestService $service): ResolutionResource
    {
        return new ResolutionResource($service->resolve($request->member(), $request->requestId(), $request->resolutionData(), $request->idempotencyKey()));
    }
}
