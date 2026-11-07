<?php

declare(strict_types=1);

namespace App\Services\Comparisons;

use App\Enums\Comparisons\ComparisonOutcome;
use App\Enums\Comparisons\ComparisonState;

/**
 * BV208 — Projection scellée du résultat d'une réconciliation. Utilisée par
 * la commande `comparisons:reconcile` et par les tests Integration. Les
 * raisons sont des étiquettes stables, pas des messages utilisateur.
 */
final class ReconciliationReport
{
    private function __construct(
        public readonly string $comparisonId,
        public readonly string $status,
        public readonly ?ComparisonState $state,
        public readonly ?ComparisonOutcome $exposedOutcome,
        public readonly string $reason,
    ) {}

    public static function notFound(string $comparisonId): self
    {
        return new self($comparisonId, 'not_found', null, null, 'unknown_comparison');
    }

    public static function alreadyFinalized(string $comparisonId, ComparisonState $state, ?ComparisonOutcome $exposed): self
    {
        return new self($comparisonId, 'already_finalized', $state, $exposed, 'idempotent_replay');
    }

    public static function noChange(string $comparisonId, ComparisonState $state, ?ComparisonOutcome $exposed): self
    {
        return new self($comparisonId, 'no_change', $state, $exposed, 'waiting_for_children');
    }

    public static function finalized(string $comparisonId, ComparisonState $state, ?ComparisonOutcome $exposed, string $reason): self
    {
        return new self($comparisonId, 'finalized', $state, $exposed, $reason);
    }
}
