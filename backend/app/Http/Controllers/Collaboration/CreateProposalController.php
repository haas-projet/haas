<?php

namespace App\Http\Controllers\Collaboration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\StoreProposalRequest;
use App\Http\Resources\Collaboration\ProposalResource;
use App\Services\Collaboration\ProposalService;
use Illuminate\Http\JsonResponse;

final class CreateProposalController extends Controller
{
    public function __invoke(StoreProposalRequest $request, ProposalService $service): JsonResponse
    {
        return (new ProposalResource($service->create($request->member(), $request->targetId(), $request->proposalData(), $request->idempotencyKey())))->response()->setStatusCode(201);
    }
}
