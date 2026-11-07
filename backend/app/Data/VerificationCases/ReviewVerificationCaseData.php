<?php

declare(strict_types=1);

namespace App\Data\VerificationCases;

use App\Enums\VerificationCases\ReviewDecision;

final readonly class ReviewVerificationCaseData
{
    public function __construct(
        public int $lockVersion,
        public ReviewDecision $decision,
        public ?string $reason,
    ) {}

    /** @return array<array-key, mixed> */
    public function payload(): array
    {
        return [
            'lock_version' => $this->lockVersion,
            'decision' => $this->decision->value,
            'reason' => $this->reason,
        ];
    }
}
