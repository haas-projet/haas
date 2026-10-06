<?php

namespace App\Http\Resources\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class AdministrationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'handle' => $this->handle, 'status' => $this->status->value, 'role' => $this->role->value, 'lock_version' => $this->security_version];
    }
}
