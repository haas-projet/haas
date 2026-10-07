<?php

namespace App\Data\Audit;

use App\Support\HelpRequests\HelpRequestContent;
use InvalidArgumentException;

final readonly class HelpRequestRevisionData
{
    /** @param list<string> $changedFields */
    public function __construct(public array $changedFields, public bool $published, public bool $hasNote)
    {
        if (array_diff($changedFields, [...HelpRequestContent::FIELDS, 'help_intent', 'technologies', 'state']) !== []) {
            throw new InvalidArgumentException('Champs de révision hors contrat.');
        }
    }
}
