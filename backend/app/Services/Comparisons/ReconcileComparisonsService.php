<?php

declare(strict_types=1);

namespace App\Services\Comparisons;

use App\Enums\Comparisons\ComparisonOutcome;
use App\Enums\Comparisons\ComparisonSide;
use App\Enums\Comparisons\ComparisonState;
use App\Enums\Lab\LabRunState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * BV208 — Réconciliation d'une comparaison B1 à partir des états terminaux
 * de ses deux enfants et, le cas échéant, de la paire de lab_results.
 *
 * Aucune exception HTTP n'est levée depuis ce fichier
 * (Tests\Architecture\DomainBoundariesTest). Les décisions sont prises sous
 * un verrou `SELECT ... FOR UPDATE` sur comparison_runs : rejouer la
 * commande sur une comparaison déjà terminale est un no-op (finalisation
 * UNIQUE). Jamais de création de seconde paire d'enfants, jamais de retry
 * automatique, jamais une réussite par défaut.
 *
 * Le worker B36/B37 n'est pas livré : en production, les lab_runs restent
 * queued, started_at reste NULL, et la seule bascule provoquée par ce
 * service est queued -> timed_out via le budget. Les scénarios improved,
 * regressed, mixed, unchanged et error ne sont visibles qu'à partir des
 * fixtures de test (backend/tests/Support/Comparisons/LabResultFixtures).
 *
 * CHECK contraignant les écritures :
 * `comparison_runs_outcome_requires_terminal CHECK (outcome IS NULL OR
 * state = 'completed')`. On persiste outcome UNIQUEMENT quand le nouvel
 * état est `completed` ; sur error et timed_out la colonne reste NULL et
 * la lecture dérive `inconclusive` via ComparisonOutcomeCalculator::expose.
 */
final class ReconcileComparisonsService
{
    public function __construct(private readonly ComparisonOutcomeCalculator $calculator) {}

    /**
     * Réconcilie la comparaison identifiée par $comparisonId.
     * - true : l'appel a produit une transition terminale (completed/error/timed_out).
     * - false : la comparaison était déjà terminale (rejeu idempotent) ou
     *   reste non terminale faute d'enfants prêts (budget non écoulé).
     *
     * La commande comparisons:reconcile est le seul chemin d'invocation ; en
     * production, elle n'est appelée par rien (aucune planification). Les
     * tests Integration l'exercent via le binding du conteneur.
     */
    public function reconcile(string $comparisonId): ReconciliationReport
    {
        return DB::connection('pgsql')->transaction(function () use ($comparisonId): ReconciliationReport {
            /** @var object{id:string,state:string,outcome:?string,created_at:string}|null $row */
            $row = DB::connection('pgsql')->selectOne(
                'SELECT id, state, outcome, created_at FROM comparison_runs WHERE id = ? FOR UPDATE',
                [$comparisonId],
            );
            if ($row === null) {
                return ReconciliationReport::notFound($comparisonId);
            }
            $currentState = ComparisonState::from((string) $row->state);
            $terminalStates = [ComparisonState::Completed, ComparisonState::Error, ComparisonState::TimedOut];
            if (in_array($currentState, $terminalStates, true)) {
                $exposed = $this->calculator->expose(
                    $currentState,
                    $row->outcome === null ? null : ComparisonOutcome::from((string) $row->outcome),
                );

                return ReconciliationReport::alreadyFinalized($comparisonId, $currentState, $exposed);
            }

            $children = DB::connection('pgsql')->select(
                'SELECT id, comparison_side, state FROM lab_runs WHERE comparison_id = ? ORDER BY comparison_side',
                [$comparisonId],
            );
            $byRole = [];
            foreach ($children as $child) {
                $byRole[(string) $child->comparison_side] = [
                    'id' => (string) $child->id,
                    'state' => LabRunState::from((string) $child->state),
                ];
            }
            $baselineChild = $byRole[ComparisonSide::Baseline->value] ?? null;
            $candidateChild = $byRole[ComparisonSide::Candidate->value] ?? null;

            $decision = $this->decide($currentState, $baselineChild, $candidateChild, (string) $row->created_at);
            if ($decision['new_state'] === $currentState) {
                $exposed = $this->calculator->expose($currentState, null);

                return ReconciliationReport::noChange($comparisonId, $currentState, $exposed);
            }

            // Transition. Pour completed, on charge les deux lab_results et
            // on appelle le calculateur ; pour error/timed_out, outcome reste
            // NULL (le CHECK l'exige).
            $outcomeToPersist = null;
            if ($decision['new_state'] === ComparisonState::Completed
                && $baselineChild !== null && $candidateChild !== null
            ) {
                $baselineResult = $this->loadAssertionMap($baselineChild['id']);
                $candidateResult = $this->loadAssertionMap($candidateChild['id']);
                $computed = $this->calculator->calculate(
                    $baselineResult,
                    $candidateResult,
                    ComparisonState::Completed,
                    $baselineChild['state'],
                    $candidateChild['state'],
                );
                $outcomeToPersist = $computed;
            }

            $now = CarbonImmutable::now('UTC');
            $update = [
                'state' => $decision['new_state']->value,
                'outcome' => $outcomeToPersist?->value,
                'finished_at' => $now->toDateTimeString(),
            ];
            DB::connection('pgsql')->table('comparison_runs')
                ->where('id', $comparisonId)
                ->update($update);

            $exposed = $this->calculator->expose($decision['new_state'], $outcomeToPersist);

            return ReconciliationReport::finalized($comparisonId, $decision['new_state'], $exposed, $decision['reason']);
        });
    }

    /**
     * @param  array{id:string,state:LabRunState}|null  $baseline
     * @param  array{id:string,state:LabRunState}|null  $candidate
     * @return array{new_state: ComparisonState, reason: string}
     */
    private function decide(ComparisonState $current, ?array $baseline, ?array $candidate, string $createdAt): array
    {
        // 1) Si un enfant est terminal en erreur, la comparaison bascule en error.
        //    « jamais de réussite par défaut » (CAHIER §44 ligne 1655).
        if (($baseline['state'] ?? null) === LabRunState::Error || ($candidate['state'] ?? null) === LabRunState::Error) {
            return ['new_state' => ComparisonState::Error, 'reason' => 'child_error'];
        }
        // 2) Un enfant terminal en délai dépassé donne timed_out.
        if (($baseline['state'] ?? null) === LabRunState::TimedOut || ($candidate['state'] ?? null) === LabRunState::TimedOut) {
            return ['new_state' => ComparisonState::TimedOut, 'reason' => 'child_timed_out'];
        }
        $terminalSuccessOrFailure = [LabRunState::Passed, LabRunState::Failed];
        $baselineReady = $baseline !== null && in_array($baseline['state'], $terminalSuccessOrFailure, true);
        $candidateReady = $candidate !== null && in_array($candidate['state'], $terminalSuccessOrFailure, true);
        // 3) Les deux enfants ont fini (passed/failed) : on peut conclure.
        if ($baselineReady && $candidateReady) {
            return ['new_state' => ComparisonState::Completed, 'reason' => 'children_ready'];
        }
        // 4) Budget global dépassé avant qu'un enfant terminal n'arrive :
        //    on consigne timed_out sans conclusion persistée (CAHIER §44
        //    ligne 1653 ; le CHECK comparison_runs_outcome_requires_terminal
        //    laisse la colonne outcome NULL).
        if ($this->budgetExceeded($createdAt)) {
            return ['new_state' => ComparisonState::TimedOut, 'reason' => 'budget_exceeded'];
        }

        // Rien à faire : on attend que les deux enfants deviennent terminaux.
        return ['new_state' => $current, 'reason' => 'waiting_for_children'];
    }

    private function budgetExceeded(string $createdAt): bool
    {
        $budget = (int) config('lab.comparison_budget_seconds', 50);
        if ($budget <= 0) {
            $budget = 50;
        }
        $created = CarbonImmutable::parse($createdAt)->utc();

        return CarbonImmutable::now('UTC')->diffInSeconds($created, true) > $budget;
    }

    /** @return array<string, bool>|null */
    private function loadAssertionMap(string $runId): ?array
    {
        /** @var object{assertions:string, complete:bool}|null $result */
        $result = DB::connection('pgsql')->selectOne(
            'SELECT assertions, complete FROM lab_results WHERE run_id = ?',
            [$runId],
        );
        if ($result === null) {
            return null;
        }
        if (! (bool) $result->complete) {
            return null;
        }
        /** @var mixed $decoded */
        $decoded = json_decode((string) $result->assertions, true);
        if (! is_array($decoded)) {
            return null;
        }
        $map = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                return null;
            }
            // Le trigger B33 (`b33_guard_result`) impose la clé `key` dans
            // chaque élément d'assertion (voir migration ligne 175). Les
            // identifiants d'assertion traversent donc `key`, pas `id`.
            $key = $row['key'] ?? null;
            $passed = $row['passed'] ?? null;
            if (! is_string($key) || $key === '' || ! is_bool($passed)) {
                return null;
            }
            $map[$key] = $passed;
        }

        return $map === [] ? null : $map;
    }
}
