<?php

namespace App\Data\HelpRequests;

use App\Rules\ProposalText;
use App\Support\HelpRequests\ResolutionContent;
use InvalidArgumentException;

final readonly class ResolutionData
{
    public string $proposalId;

    public int $lockVersion;

    public int $proposalLockVersion;

    public string $environment;

    public string $validationNote;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        if (! ResolutionContent::isValid($data) || ! is_string($data['proposal_id']) || ! is_int($data['lock_version'])
            || ! is_int($data['proposal_lock_version']) || ! is_string($data['environment']) || ! is_string($data['validation_note'])) {
            throw new InvalidArgumentException('Données de résolution hors contrat.');
        }
        $this->proposalId = strtolower($data['proposal_id']);
        $this->lockVersion = $data['lock_version'];
        $this->proposalLockVersion = $data['proposal_lock_version'];
        $this->environment = ProposalText::trim($data['environment']);
        $this->validationNote = ProposalText::trim($data['validation_note']);
    }

    /** @return array{proposal_id:string, lock_version:int, proposal_lock_version:int, environment:string, validation_note:string} */
    public function payload(): array
    {
        return ['proposal_id' => $this->proposalId, 'lock_version' => $this->lockVersion,
            'proposal_lock_version' => $this->proposalLockVersion, 'environment' => $this->environment, 'validation_note' => $this->validationNote];
    }
}
