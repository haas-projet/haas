<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Demo\RecordDemoOrderRequest;
use App\Http\Resources\Demo\DemoOrderResource;
use App\Services\Demo\RecordDemoOrderService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller mince de la brique B2 (lot B38) : délègue tout au service B2.
 * Aucune règle métier n'est portée ici. L'en-tête `X-Idempotent-Replay`
 * expose explicitement au client que la réponse courante correspond à un
 * rejeu de la même intention ; le code HTTP distingue aussi 201 (création)
 * et 200 (rejeu identique).
 */
final class RecordDemoOrderController extends Controller
{
    public function __invoke(RecordDemoOrderRequest $request, RecordDemoOrderService $service): JsonResponse
    {
        $recorded = $service->handle($request->toData(), $request->idempotencyKey());
        $resource = new DemoOrderResource($recorded->order);

        return $resource
            ->response()
            ->setStatusCode($recorded->replay ? Response::HTTP_OK : Response::HTTP_CREATED)
            ->header('X-Idempotent-Replay', $recorded->replay ? 'true' : 'false');
    }
}
