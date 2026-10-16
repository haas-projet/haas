<?php

declare(strict_types=1);

namespace App\Http\Resources\Comparisons;

use App\Data\Comparisons\ComparisonRunData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ComparisonRunData */
final class ComparisonRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ComparisonRunData $data */
        $data = $this->resource;

        // BV208 : outcome ajouté pour l'acteur propriétaire. Il peut être NULL
        // (comparaison encore queued/running) ou dérivé « inconclusive » pour
        // error/timed_out (la colonne, elle, reste NULL faute d'écriture
        // autorisée par le CHECK comparison_runs_outcome_requires_terminal).
        // La Query a déjà appliqué ComparisonOutcomeCalculator::expose : on
        // ne duplique jamais cette décision ici (CAHIER §45 ligne 1672).
        return [
            'id' => $data->id,
            'profile_id' => $data->profileId,
            'state' => $data->state->value,
            'outcome' => $data->exposedOutcome?->value,
            'created_at' => $data->createdAt,
            'started_at' => $data->startedAt,
            'finished_at' => $data->finishedAt,
            'follow_url' => $data->followUrl,
            'children_follow_urls' => $data->followUrls,
        ];
    }
}
