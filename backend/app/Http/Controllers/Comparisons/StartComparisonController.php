<?php

declare(strict_types=1);

namespace App\Http\Controllers\Comparisons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comparisons\StartComparisonRequest;
use App\Http\Resources\Comparisons\ComparisonRunResource;
use App\Models\User;
use App\Services\Comparisons\StartComparisonService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class StartComparisonController extends Controller
{
    public function __invoke(StartComparisonRequest $request, StartComparisonService $service): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        $queued = $service->handle($actor, $request->toData(), $request->idempotencyKey());

        return (new ComparisonRunResource($queued))
            ->response($request)
            ->setStatusCode(Response::HTTP_ACCEPTED)
            ->header('Cache-Control', 'no-store, private')
            ->header('Location', '/api/v1/comparisons/'.$queued->id);
    }
}
