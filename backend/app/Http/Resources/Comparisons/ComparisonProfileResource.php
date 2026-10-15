<?php

declare(strict_types=1);

namespace App\Http\Resources\Comparisons;

use App\Enums\Lab\LabScenario;
use App\Models\Comparisons\ComparisonProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ComparisonProfile */
final class ComparisonProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_key' => $this->family_key,
            'candidate_version_id' => $this->candidate_version_id,
            'input_schema_version' => $this->input_schema_version,
            'enabled' => $this->enabled,
            'allowed_scenarios' => array_map(static fn (LabScenario $s): string => $s->value, LabScenario::cases()),
        ];
    }
}
