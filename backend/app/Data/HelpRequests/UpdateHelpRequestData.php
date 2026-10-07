<?php

namespace App\Data\HelpRequests;

use App\Enums\HelpRequests\HelpIntent;
use App\Support\HelpRequests\HelpRequestContent;
use InvalidArgumentException;

final readonly class UpdateHelpRequestData
{
    /**
     * @param  array<string, ?string>  $attributes
     * @param  ?list<array{id: string, version_label: ?string}>  $technologies
     */
    public function __construct(public int $lockVersion, public array $attributes = [], public ?HelpIntent $helpIntent = null, public ?array $technologies = null, public ?string $editNote = null)
    {
        if ($lockVersion < 1 || $lockVersion >= 2147483647 || array_diff(array_keys($attributes), HelpRequestContent::FIELDS) !== []) {
            throw new InvalidArgumentException('Édition de demande hors contrat.');
        }
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $technologies = $this->technologies;
        if ($technologies !== null) {
            usort($technologies, fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        }

        return ['lock_version' => $this->lockVersion, 'attributes' => $this->attributes, 'help_intent' => $this->helpIntent?->value, 'technologies' => $technologies, 'edit_note' => $this->editNote];
    }
}
