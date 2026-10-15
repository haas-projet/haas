<?php

declare(strict_types=1);

namespace App\Http\Resources\Lab;

use App\Data\Lab\QueuedLabRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QueuedLabRun */
final class LabRunResource extends JsonResource
{
    /** @return array{id:string,state:string,scenario:string,version_id:string,created_at:string,follow_url:string} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state->value,
            'scenario' => $this->scenario,
            'version_id' => $this->versionId,
            'created_at' => $this->createdAt,
            'follow_url' => url('/api/v1/lab-runs/'.$this->id),
        ];
    }
}
