<?php

declare(strict_types=1);

namespace App\Data\VerificationCases;

final readonly class SubmitVerificationCaseData
{
    public function __construct(public int $lockVersion) {}

    /** @return array<array-key, mixed> */
    public function payload(): array
    {
        return ['lock_version' => $this->lockVersion];
    }
}
