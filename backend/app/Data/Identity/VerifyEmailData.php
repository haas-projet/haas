<?php

namespace App\Data\Identity;

final readonly class VerifyEmailData
{
    public function __construct(public string $id, public string $emailHash) {}
}
