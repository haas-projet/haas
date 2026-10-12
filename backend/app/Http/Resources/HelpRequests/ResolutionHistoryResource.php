<?php

namespace App\Http\Resources\HelpRequests;

use App\Models\Resolution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Resolution */
final class ResolutionHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...(new ResolutionResource($this->resource))->toArray($request),
            'reopening' => $this->reopening === null ? null : new RequestReopeningResource($this->reopening)];
    }
}
