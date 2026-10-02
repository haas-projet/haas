<?php

namespace App\Http\Requests\Identity;

use App\Data\Identity\VerifyEmailData;

final class VerifyEmailRequest extends ResendVerificationRequest
{
    public function authorize(): bool
    {
        return parent::authorize()
            && hash_equals($this->member()->id, (string) $this->route('id'))
            && hash_equals(sha1($this->member()->getEmailForVerification()), (string) $this->route('hash'));
    }

    public function rules(): array
    {
        return ['expires' => ['required', 'integer'], 'signature' => ['required', 'string', 'size:64']];
    }

    public function verificationData(): VerifyEmailData
    {
        return new VerifyEmailData((string) $this->route('id'), (string) $this->route('hash'));
    }
}
