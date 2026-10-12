<?php

namespace App\Data\Projects;

use App\Support\Projects\ProjectContent;
use InvalidArgumentException;

final readonly class UpdateProjectData
{
    /** @var array<string, mixed> */
    public array $attributes;

    /** @var list<string>|null */
    public ?array $technologyIds;

    /** @param array<string, mixed> $attributes
     * @param  list<string>|null  $technologyIds
     */
    public function __construct(public int $lockVersion, array $attributes = [], ?array $technologyIds = null)
    {
        if ($lockVersion < 1 || $lockVersion > 2147483646 || ($attributes === [] && $technologyIds === null)
            || array_diff(array_keys($attributes), ProjectContent::FIELDS) !== []
            || ! ProjectContent::isValid([...$attributes, ...($technologyIds === null ? [] : ['technology_ids' => $technologyIds])])) {
            throw new InvalidArgumentException('Modification de projet hors contrat.');
        }
        $this->attributes = ProjectContent::normalize($attributes);
        if ($technologyIds === null) {
            $this->technologyIds = null;
        } else {
            $ids = array_map(strtolower(...), $technologyIds);
            sort($ids, SORT_STRING);
            $this->technologyIds = $ids;
        }
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return ['lock_version' => $this->lockVersion, 'attributes' => $this->attributes, 'technology_ids' => $this->technologyIds];
    }
}
