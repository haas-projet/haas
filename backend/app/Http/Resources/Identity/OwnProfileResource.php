<?php

namespace App\Http\Resources\Identity;

use Illuminate\Http\Request;

final class OwnProfileResource extends PublicProfileResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'lock_version' => $this->profile->lock_version ?? 0,
            'can_update' => $this->resource->can('updateProfile', $this->resource),
        ]);
    }
}
