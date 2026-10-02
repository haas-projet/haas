<?php

namespace App\Http\Resources\Identity;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class RegisteredMemberResource extends JsonResource
{
    /** @return array{id: string, handle: string, email_verified: bool} */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'handle' => $this->handle, 'email_verified' => $this->hasVerifiedEmail()];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(201);
        $response->headers->set('Cache-Control', 'no-store');
    }
}
