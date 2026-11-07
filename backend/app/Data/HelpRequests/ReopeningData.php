<?php

namespace App\Data\HelpRequests;

use App\Rules\ProposalText;
use App\Support\HelpRequests\ReopeningContent;
use InvalidArgumentException;

final readonly class ReopeningData
{
    public int $lockVersion;

    public string $reason;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        if (! ReopeningContent::isValid($data) || ! is_int($data['lock_version']) || ! is_string($data['reason'])) {
            throw new InvalidArgumentException('Données de réouverture hors contrat.');
        }
        $this->lockVersion = $data['lock_version'];
        $this->reason = ProposalText::trim($data['reason']);
    }

    /** @return array{lock_version: int, reason: string} */
    public function payload(): array
    {
        return ['lock_version' => $this->lockVersion, 'reason' => $this->reason];
    }
}
