<?php

namespace App\Http\Resources\Moderation;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Report */
class ReportReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at ?? throw new \LogicException('Le signalement doit être persisté.');

        return ['id' => $this->id, 'resource_type' => $this->resource_type, 'resource_id' => $this->resource_id,
            'status' => $this->status, 'lock_version' => $this->lock_version, 'created_at' => $createdAt->toIso8601String()];
    }
}
