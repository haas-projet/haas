<?php

namespace App\Http\Requests\Identity;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;

class ResendVerificationRequest extends AccountMailRequest
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
        return $this->member()->can('manageAccountMail', $this->member());
    }

    public function rules(): array
    {
        return [];
    }
}
