<?php

namespace App\Http\Resources\HelpRequests;

use App\Models\HelpRequestRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HelpRequestRevision */
final class HelpRequestRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'request_version' => $this->request_version, 'action' => $this->action,
            'changed_fields' => $this->changed_fields, 'edit_note' => $this->edit_note, 'occurred_at' => $this->occurred_at->toISOString()];
    }
}
