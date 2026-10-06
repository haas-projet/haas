<?php

namespace App\Http\Requests\Identity;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;

final class MeRequest extends ReadAccountRequest
{
    public function member(): User
    {
        $user = $this->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    public function authorize(): bool
    {
        return $this->member()->can('view', $this->member());
    }
}
