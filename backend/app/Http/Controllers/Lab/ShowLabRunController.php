<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\ShowLabRunRequest;
use App\Http\Resources\Lab\LabRunResource;
use App\Models\User;
use App\Services\Lab\StartLabRunService;
use Illuminate\Http\JsonResponse;

final class ShowLabRunController extends Controller
{
    public function __invoke(ShowLabRunRequest $request, string $id, StartLabRunService $service): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $run = $service->show($actor, $id);

        return (new LabRunResource($run))->response()->header('Cache-Control', 'no-store');
    }
}
