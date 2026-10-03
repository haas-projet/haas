<?php

namespace App\Data\Audit;

use InvalidArgumentException;

final readonly class ProfileRevisionData
{
    /** @var list<string> */
    public array $changedFields;

    /** @param array<array-key, mixed> $changedFields */
    public function __construct(array $changedFields)
    {
        if (! array_is_list($changedFields)) {
            throw new InvalidArgumentException('Champs de révision hors contrat.');
        }
        $validated = [];
        foreach ($changedFields as $field) {
            if (! is_string($field) || ! in_array($field, ['bio', 'country', 'primary_language', 'github_url', 'technology_ids'], true)
                || in_array($field, $validated, true)) {
                throw new InvalidArgumentException('Champs de révision hors contrat.');
            }
            $validated[] = $field;
        }
        $this->changedFields = $validated;
    }
}
