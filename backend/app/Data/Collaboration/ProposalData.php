<?php

namespace App\Data\Collaboration;

use App\Rules\ProposalText;
use App\Support\Collaboration\ProposalContent;
use InvalidArgumentException;

final readonly class ProposalData
{
    /** @var array<string, string|null> */
    public array $changes;

    public ?int $lockVersion;

    /** @param array<string, mixed> $changes */
    public function __construct(array $changes, mixed $lockVersion = null)
    {
        if ($changes === [] || array_diff(array_keys($changes), ProposalContent::FIELDS) !== []
            || ($lockVersion !== null && (! is_int($lockVersion) || $lockVersion < 1 || $lockVersion >= 2147483647))) {
            throw new InvalidArgumentException('Données de proposition hors contrat.');
        }
        $source = $changes;
        foreach ($changes as $field => $value) {
            if ($field === 'code' && $value === null) {
                continue;
            }
            if (! is_string($value)) {
                throw new InvalidArgumentException('Type de contenu hors contrat.');
            }
            if ($field !== 'code') {
                $changes[$field] = ProposalText::trim($value);
            }
        }
        if ($lockVersion === null && array_diff(array_slice(ProposalContent::FIELDS, 0, 4), array_keys($changes)) !== []) {
            throw new InvalidArgumentException('Les quatre descriptions sont requises.');
        }
        if (! ProposalContent::isValid($source, $lockVersion !== null)) {
            throw new InvalidArgumentException('Contenu de proposition hors contrat.');
        }
        $this->changes = $changes;
        $this->lockVersion = $lockVersion;
    }

    /** @return array{changes: array<string, string|null>, lock_version: ?int} */
    public function payload(): array
    {
        return ['changes' => $this->changes, 'lock_version' => $this->lockVersion];
    }
}
