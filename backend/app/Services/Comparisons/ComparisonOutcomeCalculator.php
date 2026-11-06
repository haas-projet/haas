<?php

declare(strict_types=1);

namespace App\Services\Comparisons;

use App\Enums\Comparisons\ComparisonOutcome;
use App\Enums\Comparisons\ComparisonState;
use App\Enums\Lab\LabRunState;

/**
 * BV208 — Partie pure du domaine : conclut une comparaison B1 à partir des
 * résultats synthétiques de ses deux enfants (CAHIER_DES_CHARGES.md:1639-1655).
 *
 * Hors dépendance Laravel : rien ici n'ouvre une connexion, ne touche la
 * requête HTTP ou le conteneur ; Tests\Architecture\DomainBoundariesTest le
 * garantit.
 *
 * Deux méthodes, une règle centralisée :
 *
 * - `calculate(...)` : logique pure appelée par ReconcileComparisonsService
 *   quand la paire d'enfants est connue. Retourne la conclusion machine,
 *   persistée en colonne outcome UNIQUEMENT si l'état bascule à `completed`
 *   (CHECK comparison_runs_outcome_requires_terminal).
 * - `expose(...)` : dérivation UNIQUE utilisée à la fois par le service
 *   (sortie de la commande comparisons:reconcile) et par ComparisonRunResource
 *   à la lecture. Elle expose « inconclusive » pour error/timed_out alors que
 *   la colonne vaut NULL (question ouverte à confirmer en revue, voir
 *   docs/execution/participants/mdev44-code/HANDOFF.md).
 */
final class ComparisonOutcomeCalculator
{
    /**
     * Signature pure. Les tableaux associent identifiant d'assertion (clé
     * non vide) à réussite (booléen strict). L'ordre des clés est indifférent.
     *
     * @param  array<string, bool>|null  $baseline  Résultats synthétiques du côté baseline, null si absent.
     * @param  array<string, bool>|null  $candidate  Résultats synthétiques du côté candidate, null si absent.
     */
    public function calculate(
        ?array $baseline,
        ?array $candidate,
        ComparisonState $state,
        ?LabRunState $baselineState,
        ?LabRunState $candidateState,
    ): ComparisonOutcome {
        // 1) État global en panne / délai dépassé.
        if ($state === ComparisonState::Error || $state === ComparisonState::TimedOut) {
            return ComparisonOutcome::Inconclusive;
        }
        // 2) Un enfant absent (sans état connu) → inconclusive.
        if ($baselineState === null || $candidateState === null) {
            return ComparisonOutcome::Inconclusive;
        }
        // 3) Un enfant non terminal « complète » (queued, running, error, timed_out).
        $nonTerminal = [LabRunState::Queued, LabRunState::Running, LabRunState::Error, LabRunState::TimedOut];
        if (in_array($baselineState, $nonTerminal, true) || in_array($candidateState, $nonTerminal, true)) {
            return ComparisonOutcome::Inconclusive;
        }
        // 4) Résultats absents.
        if ($baseline === null || $candidate === null) {
            return ComparisonOutcome::Inconclusive;
        }
        // 5) Résultats incomplets (clé non chaîne non vide, valeur non booléenne, tableau vide).
        if (! $this->isComplete($baseline) || ! $this->isComplete($candidate)) {
            return ComparisonOutcome::Inconclusive;
        }
        // 6) Les ENSEMBLES d'identifiants d'assertion diffèrent → inconclusive, jamais une victoire.
        $baselineKeys = array_keys($baseline);
        $candidateKeys = array_keys($candidate);
        sort($baselineKeys);
        sort($candidateKeys);
        if ($baselineKeys !== $candidateKeys) {
            return ComparisonOutcome::Inconclusive;
        }
        // 7) Assertion par assertion : improved / regressed / mixed / unchanged.
        $improved = 0;
        $regressed = 0;
        foreach ($baselineKeys as $key) {
            $before = $baseline[$key];
            $after = $candidate[$key];
            if ($before === false && $after === true) {
                $improved++;
            } elseif ($before === true && $after === false) {
                $regressed++;
            }
        }
        if ($improved > 0 && $regressed > 0) {
            return ComparisonOutcome::Mixed;
        }
        if ($improved > 0) {
            return ComparisonOutcome::Improved;
        }
        if ($regressed > 0) {
            return ComparisonOutcome::Regressed;
        }

        // Vecteur identique, y compris deux échecs sur les mêmes assertions.
        return ComparisonOutcome::Unchanged;
    }

    /**
     * Règle UNIQUE de dérivation à la lecture, partagée par la commande
     * comparisons:reconcile et ComparisonRunResource (via la Query). Le CHECK
     * `comparison_runs_outcome_requires_terminal` interdit d'écrire la colonne
     * outcome tant que state ≠ 'completed' : pour error/timed_out on expose
     * « inconclusive » dérivé alors que la colonne vaut NULL. Jamais de
     * duplication de cette décision.
     *
     * - state=completed → la colonne (déjà `inconclusive` possible si les
     *   identifiants d'assertion diffèrent) ;
     * - state=error|timed_out → `inconclusive` dérivé ;
     * - state=queued|running → `null` (rien à exposer, rien n'est conclu).
     */
    public function expose(ComparisonState $state, ?ComparisonOutcome $column): ?ComparisonOutcome
    {
        return match ($state) {
            ComparisonState::Completed => $column,
            ComparisonState::Error, ComparisonState::TimedOut => ComparisonOutcome::Inconclusive,
            ComparisonState::Queued, ComparisonState::Running => null,
        };
    }

    /**
     * Garde-fou défensif : les tests Unit poussent volontairement des
     * clés et valeurs mal typées (voir test_non_boolean_value_is_inconclusive).
     *
     * @param  array<mixed, mixed>  $results
     */
    private function isComplete(array $results): bool
    {
        if ($results === []) {
            return false;
        }
        foreach ($results as $key => $value) {
            if (! is_string($key) || $key === '') {
                return false;
            }
            if (! is_bool($value)) {
                return false;
            }
        }

        return true;
    }
}
