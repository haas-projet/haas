<?php

declare(strict_types=1);

namespace App\Http\Resources\Capsules;

use App\Models\Capsules\ReuseReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * B28 — Resource d'un retour : marque toujours « déclaration humaine »
 * (CAHIER_DES_CHARGES.md:500). Jamais de score, jamais `user_id` cru.
 *
 * @property-read ReuseReport $resource
 */
final class ReuseReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ReuseReport $report */
        $report = $this->resource;

        return [
            'id' => $report->id,
            'version_id' => $report->version_id,
            'environment' => $report->environment,
            'reported_at' => $report->reported_at->format('Y-m-d'),
            'outcome' => $report->outcome->value,
            'observation' => $report->observation,
            'is_internal' => (bool) $report->is_internal,
            'revision' => (int) $report->revision,
            'kind' => 'human_declaration',
        ];
    }
}
