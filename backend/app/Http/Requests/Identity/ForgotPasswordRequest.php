<?php

namespace App\Http\Requests\Identity;

use App\Data\Identity\ForgotPasswordData;

final class ForgotPasswordRequest extends AccountMailRequest
{
    public function rules(): array
    {
        return ['email' => ['bail', 'required', 'string', 'email:rfc', 'max:255', 'not_regex:/\s/u']];
    }

    public function resetLinkData(): ForgotPasswordData
    {
        return new ForgotPasswordData($this->validated('email'));
    }
}
