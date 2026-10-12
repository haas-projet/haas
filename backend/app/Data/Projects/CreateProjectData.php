<?php

namespace App\Data\Projects;

use App\Support\Projects\ProjectContent;
use InvalidArgumentException;

final readonly class CreateProjectData
{
    /** @var array<string, mixed> */
    public array $attributes;

    /** @var list<string> */
    public array $technologyIds;

    /** @param array<string, mixed> $attributes
     * @param  list<string>  $technologyIds
     */
    public function __construct(array $attributes = [], array $technologyIds = [])
    {
        if (array_diff(array_keys($attributes), ProjectContent::FIELDS) !== []
            || ! ProjectContent::isValid([...$attributes, 'technology_ids' => $technologyIds])) {
            throw new InvalidArgumentException('Création de projet hors contrat.');
        }
        $this->attributes = array_replace(array_fill_keys(ProjectContent::FIELDS, null), ['project_stage' => 'idea'], ProjectContent::normalize($attributes));
        $ids = array_map(strtolower(...), $technologyIds);
        sort($ids, SORT_STRING);
        $this->technologyIds = $ids;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return ['attributes' => $this->attributes, 'technology_ids' => $this->technologyIds];
    }
}
