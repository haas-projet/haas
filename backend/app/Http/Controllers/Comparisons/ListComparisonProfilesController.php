<?php

declare(strict_types=1);

namespace App\Http\Controllers\Comparisons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comparisons\ListComparisonProfilesRequest;
use App\Http\Resources\Comparisons\ComparisonProfileResource;
use App\Queries\Comparisons\ComparisonProfileQuery;
use Illuminate\Http\JsonResponse;

final class ListComparisonProfilesController extends Controller
{
    public function __invoke(ListComparisonProfilesRequest $request, string $id, ComparisonProfileQuery $query): JsonResponse
    {
        $profiles = $query->listForVersion($id);

        return ComparisonProfileResource::collection(collect($profiles))
            ->response($request)
            ->header('Cache-Control', 'no-store, private');
    }
}
