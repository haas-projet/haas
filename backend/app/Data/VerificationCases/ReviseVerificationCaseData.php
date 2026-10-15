<?php

declare(strict_types=1);

namespace App\Data\VerificationCases;

final readonly class ReviseVerificationCaseData
{
    /** @param array{title:string,context:string,steps:string,expected:string,observed:?string,environment:?string} $body */
    public function __construct(public int $lockVersion, public array $body) {}

    /** @return array<array-key, mixed> */
    public function payload(): array
    {
        return ['lock_version' => $this->lockVersion, 'body' => $this->body];
    }
}
