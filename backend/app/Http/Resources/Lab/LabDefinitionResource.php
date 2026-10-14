<?php

declare(strict_types=1);

namespace App\Http\Resources\Lab;

use App\Data\Lab\LabCapabilityData;
use App\Enums\Lab\LabScenario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LabCapabilityData */
final class LabDefinitionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $definition = $this->definition;

        return ['version_id' => $this->versionId, 'available' => $this->available(), 'definition' => $definition === null ? null : [
            'id' => $definition->id, 'release' => $definition->release, 'module_version' => $definition->moduleVersion,
            'code_digest' => $definition->codeDigest, 'suite_digest' => $definition->suiteDigest, 'version_digest' => $definition->versionDigest,
            'scenarios' => array_map(static fn (LabScenario $scenario): array => ['key' => $scenario->value, 'label' => $scenario->label()], LabScenario::cases()),
        ]];
    }
}
