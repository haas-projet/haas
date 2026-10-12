<?php

declare(strict_types=1);

namespace App\Data\Lab;

final readonly class LabCapabilityData
{
    public function __construct(public string $versionId, public ?LabDefinitionData $definition) {}

    public function available(): bool
    {
        return $this->definition !== null;
    }
}
