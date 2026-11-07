<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StartLabRunRequest;
use App\Http\Resources\Lab\LabRunResource;
use App\Models\User;
use App\Services\Lab\StartLabRunService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class StartLabRunController extends Controller
{
    public function __invoke(StartLabRunRequest $request, StartLabRunService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $queued = $service->handle($actor, $request->toData(), $request->idempotencyKey());

        return (new LabRunResource($queued))->response()
            ->setStatusCode(Response::HTTP_ACCEPTED)
            ->header('Cache-Control', 'no-store')
            ->header('Location', url('/api/v1/lab-runs/'.$queued->id));
    }
}
