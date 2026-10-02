<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class PaginatedResourceCollection extends ResourceCollection
{
    /** @param LengthAwarePaginator<int, mixed> $resource */
    public function __construct(LengthAwarePaginator $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array{meta: array<string, mixed>}
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return ['meta' => array_intersect_key($paginated, array_flip([
            'current_page', 'per_page', 'last_page', 'total', 'from', 'to',
        ]))];
    }
}
