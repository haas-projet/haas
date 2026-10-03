<?php

namespace App\Data\Identity;

use SensitiveParameter;
use SensitiveParameterValue;

final readonly class ResetPasswordData
{
    public SensitiveParameterValue $token;

    public SensitiveParameterValue $password;

    public function __construct(public string $email, #[SensitiveParameter] string $token, #[SensitiveParameter] string $password)
    {
        $this->token = new SensitiveParameterValue($token);
        $this->password = new SensitiveParameterValue($password);
    }
}
