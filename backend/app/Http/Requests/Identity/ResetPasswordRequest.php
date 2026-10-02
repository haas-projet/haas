<?php

namespace App\Http\Requests\Identity;

use App\Data\Identity\ResetPasswordData;

final class ResetPasswordRequest extends AccountMailRequest
{
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255', 'not_regex:/\s/u'],
            'token' => ['bail', 'required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'password' => ['bail', 'required', 'string', 'min:12', 'max:128'],
            'password_confirmation' => ['bail', 'required', 'string', 'same:password'],
        ];
    }

    public function passwordData(): ResetPasswordData
    {
        return new ResetPasswordData($this->validated('email'), $this->validated('token'), $this->validated('password'));
    }
}
