<?php

declare(strict_types=1);

namespace App\Data\Comparisons;

use App\Enums\Comparisons\ComparisonOutcome;
use App\Enums\Comparisons\ComparisonState;

/**
 * Projection publique BV205+BV208 d'un comparison_run : jamais le digest
 * d'entrée, le seed, l'env_snapshot ou les digests des enfants. BV208 ajoute
 * l'outcome exposé par ComparisonRunResource : la propriété portée ici est
 * nullable. Pour error et timed_out, la colonne outcome vaut toujours NULL
 * (CHECK comparison_runs_outcome_requires_terminal) ; la Query dérive
 * `inconclusive` via ComparisonOutcomeCalculator::expose avant d'instancier
 * cet objet. Volontairement : pas de « passed », pas de digest brut.
 */
final class ComparisonRunData
{
    /**
     * @param  list<string>  $followUrls  URLs de suivi pour chaque enfant lab_run (ordre baseline, candidate)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $profileId,
        public readonly ComparisonState $state,
        public readonly ?ComparisonOutcome $exposedOutcome,
        public readonly string $createdAt,
        public readonly ?string $startedAt,
        public readonly ?string $finishedAt,
        public readonly string $followUrl,
        public readonly array $followUrls,
    ) {}
}
