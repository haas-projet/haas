<?php

declare(strict_types=1);

namespace App\Queries\Comparisons;

use App\Data\Comparisons\ComparisonRunData;
use App\Enums\Comparisons\ComparisonSide;
use App\Models\Comparisons\ComparisonRun;
use App\Models\Lab\LabRun;
use App\Models\User;
use App\Services\Comparisons\ComparisonOutcomeCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lecture d'une comparaison, réservée à son acteur (CAHIER §45 ligne 1672).
 * HAAS ne révèle jamais à autrui l'existence d'une comparaison : actor_id
 * discordant = 404, même format qu'un UUID inconnu.
 */
final class ComparisonRunQuery
{
    public function __construct(private readonly ComparisonOutcomeCalculator $calculator) {}

    public function get(User $actor, string $id): ComparisonRunData
    {
        $run = ComparisonRun::query()
            ->whereKey($id)
            ->where('actor_id', $actor->id)
            ->first();
        if ($run === null) {
            throw new NotFoundHttpException;
        }
        $children = LabRun::query()
            ->where('comparison_id', $run->id)
            ->orderBy('comparison_side')
            ->get();
        $followUrls = $this->childrenFollowUrls($children);

        // BV208 : dérivation unique via le calculateur. Pour error/timed_out
        // la colonne `outcome` vaut NULL (CHECK ...outcome_requires_terminal)
        // et expose() rend `inconclusive` ; pour queued/running on expose null.
        $exposed = $this->calculator->expose($run->state, $run->outcome);

        return new ComparisonRunData(
            id: $run->id,
            profileId: $run->profile_id,
            state: $run->state,
            exposedOutcome: $exposed,
            createdAt: $this->isoOrNull($run->created_at) ?? '',
            startedAt: $this->isoOrNull($run->started_at),
            finishedAt: $this->isoOrNull($run->finished_at),
            followUrl: '/api/v1/comparisons/'.$run->id,
            followUrls: $followUrls,
        );
    }

    /**
     * @param  Collection<int, LabRun>  $children
     * @return list<string>
     */
    private function childrenFollowUrls(Collection $children): array
    {
        $byRole = [];
        foreach ($children as $child) {
            $side = $child->getAttribute('comparison_side');
            if ($side === ComparisonSide::Baseline->value || $side === ComparisonSide::Candidate->value) {
                $byRole[$side] = '/api/v1/lab-runs/'.(string) $child->getAttribute('id');
            }
        }
        $ordered = [];
        foreach ([ComparisonSide::Baseline->value, ComparisonSide::Candidate->value] as $side) {
            if (isset($byRole[$side])) {
                $ordered[] = $byRole[$side];
            }
        }

        return $ordered;
    }

    private function isoOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->utc()->toIso8601ZuluString();
    }
}
