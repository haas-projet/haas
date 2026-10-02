<?php

namespace App\Data\Identity;

use SensitiveParameter;
use SensitiveParameterValue;

final readonly class RegisterMemberData
{
    public SensitiveParameterValue $password;

    public function __construct(
        public string $handle,
        public string $email,
        #[SensitiveParameter] string $password,
        public string $termsVersion,
    ) {
        $this->password = new SensitiveParameterValue($password);
    }
}
