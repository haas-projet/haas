<?php

namespace App\Http\Resources\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class SessionMemberResource extends JsonResource
{
    /** @return array{id: string, handle: string, email_verified: bool} */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'handle' => $this->handle, 'email_verified' => $this->hasVerifiedEmail()];
    }
}
