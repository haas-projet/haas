<?php

declare(strict_types=1);

namespace App\Http\Resources\VerificationCases;

use App\Data\VerificationCases\VerificationSummaryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BV209 — Expose la fiche de vérification versionnée à l'identique pour
 * anonymes et membres (CAHIER_DES_CHARGES.md:1665). Jamais reviewer_id,
 * notes de revue, courriel, chemin privé d'artefact ni empreinte de notice.
 * Les blocs sont renvoyés DISTINCTS (jamais agrégés en un score ou un
 * badge) ; chaque élément de preuve porte sa `source` stable (ci | lab |
 * human_declaration). Cache-Control no-store, private côté contrôleur.
 */
final class VerificationSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var VerificationSummaryData $data */
        $data = $this->resource;

        return [
            'version' => [
                'id' => $data->versionId,
                'capsule_slug' => $data->capsuleSlug,
                'version_label' => $data->versionLabel,
                'state' => 'published',
                'published_at' => $data->publishedAtIso,
            ],
            'limits' => $data->limits,
            'contributors' => $data->contributors,
            'cases_examined' => $data->casesExamined,
            'observations_declared' => $data->observationsDeclared,
            'lab_evidence' => [
                'available' => $data->labEvidence->available,
                'message' => $data->labEvidence->message,
                'runs' => $data->labEvidence->runs,
                'comparisons' => $data->labEvidence->comparisons,
            ],
        ];
    }
}
