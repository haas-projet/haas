<?php

namespace App\Http\Controllers\Collaboration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\UpdateProposalRequest;
use App\Http\Resources\Collaboration\ProposalResource;
use App\Services\Collaboration\ProposalService;

final class UpdateProposalController extends Controller
{
    public function __invoke(UpdateProposalRequest $request, ProposalService $service): ProposalResource
    {
        return new ProposalResource($service->update($request->member(), $request->targetId(), $request->proposalData(), $request->idempotencyKey()));
    }
}
