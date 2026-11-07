<?php

declare(strict_types=1);

namespace App\Data\VerificationCases;

final readonly class WithdrawVerificationCaseData
{
    public function __construct(public int $lockVersion, public string $reason) {}

    /** @return array<array-key, mixed> */
    public function payload(): array
    {
        return ['lock_version' => $this->lockVersion, 'reason' => $this->reason];
    }
}
