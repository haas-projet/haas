<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Http\Requests\Lab\ShowLabDefinitionRequest;
use App\Http\Resources\Lab\LabDefinitionResource;
use App\Queries\Lab\LabDefinitionQuery;
use Illuminate\Http\JsonResponse;

final class ShowLabDefinitionController
{
    public function __invoke(ShowLabDefinitionRequest $request, string $id, LabDefinitionQuery $definitions): JsonResponse
    {
        return (new LabDefinitionResource($definitions->get($id)))->response()->header('Cache-Control', 'no-store');
    }
}
