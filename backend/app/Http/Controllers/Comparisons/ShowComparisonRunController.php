<?php

declare(strict_types=1);

namespace App\Http\Controllers\Comparisons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comparisons\ShowComparisonRunRequest;
use App\Http\Resources\Comparisons\ComparisonRunResource;
use App\Models\User;
use App\Queries\Comparisons\ComparisonRunQuery;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

final class ShowComparisonRunController extends Controller
{
    public function __invoke(ShowComparisonRunRequest $request, string $id, ComparisonRunQuery $query): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        return (new ComparisonRunResource($query->get($actor, $id)))
            ->response($request)
            ->header('Cache-Control', 'no-store, private');
    }
}
