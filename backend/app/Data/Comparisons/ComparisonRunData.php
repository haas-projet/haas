<?php

declare(strict_types=1);

namespace App\Data\Comparisons;

use App\Enums\Comparisons\ComparisonState;

/**
 * Projection publique BV205 d'un comparison_run. L'outcome machine et le
 * digest d'entrée restent internes (BV208 fera la révélation contrôlée du
 * résultat). Volontairement : pas de « passed », pas de digest brut.
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
        public readonly string $createdAt,
        public readonly ?string $startedAt,
        public readonly ?string $finishedAt,
        public readonly string $followUrl,
        public readonly array $followUrls,
    ) {}
}
