<?php

namespace App\Http\Resources\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class MeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'handle' => $this->handle,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'role' => $this->role->value,
            'status' => $this->status->value,
            'is_demo' => $this->is_demo,
            'can' => [
                'manage_account_mail' => $this->resource->can('manageAccountMail', $this->resource),
                'update_profile' => $this->resource->can('updateProfile', $this->resource),
                'participate' => $this->resource->can('participate', User::class),
                'moderate' => $this->resource->can('moderate', User::class),
                'administer' => $this->resource->can('administer', User::class),
            ],
        ];
    }
}
