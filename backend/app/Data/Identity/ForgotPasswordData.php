<?php

namespace App\Data\Identity;

final readonly class ForgotPasswordData
{
    public function __construct(public string $email) {}
}
