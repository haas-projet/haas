<?php

namespace App\Http\Requests\Identity;

use App\Http\Requests\PaginatedRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;

final class AdministrationRequest extends PaginatedRequest
{
    public function member(): User
    {
        return $this->user() instanceof User ? $this->user() : throw new AuthenticationException;
    }

    public function authorize(): bool
    {
        return $this->member()->can('administer', User::class);
    }
}
