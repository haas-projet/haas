<?php

namespace App\Http\Resources\HelpRequests;

use App\Http\Resources\PaginatedResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\Collects;

#[Collects(ResolutionHistoryResource::class)]
final class ResolutionHistoryCollection extends PaginatedResourceCollection
{
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }
}
