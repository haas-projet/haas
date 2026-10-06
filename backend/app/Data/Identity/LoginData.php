<?php

namespace App\Data\Identity;

use SensitiveParameter;
use SensitiveParameterValue;

final readonly class LoginData
{
    public SensitiveParameterValue $password;

    public function __construct(public string $email, #[SensitiveParameter] string $password)
    {
        $this->password = new SensitiveParameterValue($password);
    }
}
