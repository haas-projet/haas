<?php

declare(strict_types=1);

namespace App\Http\Resources\Comparisons;

use App\Data\Comparisons\ComparisonRunData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ComparisonRunData */
final class ComparisonRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ComparisonRunData $data */
        $data = $this->resource;

        return [
            'id' => $data->id,
            'profile_id' => $data->profileId,
            'state' => $data->state->value,
            'created_at' => $data->createdAt,
            'started_at' => $data->startedAt,
            'finished_at' => $data->finishedAt,
            'follow_url' => $data->followUrl,
            'children_follow_urls' => $data->followUrls,
        ];
    }
}
