<?php

declare(strict_types=1);

namespace Tests\Integration\Comparisons;

use App\Enums\Comparisons\ComparisonOutcome;
use App\Enums\Comparisons\ComparisonSide;
use App\Enums\Comparisons\ComparisonState;
use App\Enums\Lab\LabRunState;
use App\Models\Comparisons\ComparisonRun;
use App\Models\User;
use App\Services\Comparisons\ReconcileComparisonsService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;
use Tests\Support\Comparisons\ComparisonRunFixtures;
use Tests\Support\Comparisons\LabResultFixtures;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\Mdev44\DomainTables;

/**
 * BV208 — Finalisation unique d'une comparaison à partir de fixtures de
 * test (jamais une preuve d'exécution ; aucun worker livré, worker non
 * livré donc les runs restent queued en production).
 */
final class ReconcileComparisonsServiceTest extends PostgresTestCase
{
    use ComparisonRunFixtures, DatabaseMigrations, LabResultFixtures, RegistryFixtures;

    protected function tearDown(): void
    {
        try {
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_both_children_passed_with_improvement_is_completed_and_improved(): void
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $definitionId = $this->definition($version);
        $actor = User::factory()->verified()->create();
        $comparison = $this->pendingComparisonRun($profileId, $actor);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Baseline, $version);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Candidate, $version);
        $this->recordTerminalResult($comparison, ComparisonSide::Baseline, LabRunState::Failed, ['a' => false, 'b' => true]);
        $this->recordTerminalResult($comparison, ComparisonSide::Candidate, LabRunState::Passed, ['a' => true, 'b' => true]);

        $report = app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        $this->assertSame('finalized', $report->status);
        $this->assertSame(ComparisonState::Completed, $report->state);
        $this->assertSame(ComparisonOutcome::Improved, $report->exposedOutcome);
        $this->assertDatabaseHas('comparison_runs', [
            'id' => $comparison->id,
            'state' => 'completed',
            'outcome' => 'improved',
        ]);
    }

    public function test_regression_is_persisted_as_regressed(): void
    {
        $comparison = $this->setupComparisonWithQueuedChildren();
        $this->recordTerminalResult($comparison, ComparisonSide::Baseline, LabRunState::Passed, ['a' => true]);
        $this->recordTerminalResult($comparison, ComparisonSide::Candidate, LabRunState::Failed, ['a' => false]);

        $report = app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        $this->assertSame(ComparisonOutcome::Regressed, $report->exposedOutcome);
        $this->assertDatabaseHas('comparison_runs', ['id' => $comparison->id, 'outcome' => 'regressed']);
    }

    public function test_mixed_combines_improvement_and_regression(): void
    {
        $comparison = $this->setupComparisonWithQueuedChildren();
        $this->recordTerminalResult($comparison, ComparisonSide::Baseline, LabRunState::Failed, ['a' => false, 'b' => true]);
        $this->recordTerminalResult($comparison, ComparisonSide::Candidate, LabRunState::Failed, ['a' => true, 'b' => false]);

        $report = app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        $this->assertSame(ComparisonOutcome::Mixed, $report->exposedOutcome);
    }

    public function test_unchanged_with_two_identical_failures(): void
    {
        $comparison = $this->setupComparisonWithQueuedChildren();
        $this->recordTerminalResult($comparison, ComparisonSide::Baseline, LabRunState::Failed, ['a' => false, 'b' => false]);
        $this->recordTerminalResult($comparison, ComparisonSide::Candidate, LabRunState::Failed, ['a' => false, 'b' => false]);

        $report = app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        $this->assertSame(ComparisonState::Completed, $report->state);
        $this->assertSame(ComparisonOutcome::Unchanged, $report->exposedOutcome);
    }

    public function test_child_error_marks_comparison_error_without_persisted_outcome(): void
    {
        $comparison = $this->setupComparisonWithQueuedChildren();
        $this->markChildError($comparison, ComparisonSide::Baseline);

        $report = app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        $this->assertSame(ComparisonState::Error, $report->state);
        $this->assertSame(ComparisonOutcome::Inconclusive, $report->exposedOutcome, 'Dérivation à la lecture.');
        $this->assertDatabaseHas('comparison_runs', [
            'id' => $comparison->id,
            'state' => 'error',
            'outcome' => null,
        ]);
    }

    public function test_child_timed_out_marks_comparison_timed_out(): void
    {
        $comparison = $this->setupComparisonWithQueuedChildren();
        $this->markChildTimedOut($comparison, ComparisonSide::Candidate);

        $report = app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        $this->assertSame(ComparisonState::TimedOut, $report->state);
        $this->assertSame(ComparisonOutcome::Inconclusive, $report->exposedOutcome);
        $this->assertDatabaseHas('comparison_runs', [
            'id' => $comparison->id,
            'state' => 'timed_out',
            'outcome' => null,
        ]);
    }

    public function test_result_absent_keeps_the_comparison_waiting(): void
    {
        $comparison = $this->setupComparisonWithQueuedChildren();

        $report = app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        $this->assertSame('no_change', $report->status);
        $this->assertSame(ComparisonState::Queued, $report->state);
        $this->assertNull($report->exposedOutcome);
    }

    public function test_budget_exceeded_turns_queued_into_timed_out(): void
    {
        $comparison = $this->setupComparisonWithQueuedChildren();
        $this->ageComparisonBeyondBudget($comparison);

        $report = app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        $this->assertSame(ComparisonState::TimedOut, $report->state);
        $this->assertSame(ComparisonOutcome::Inconclusive, $report->exposedOutcome);
    }

    public function test_replay_on_finalized_comparison_is_a_no_op(): void
    {
        $comparison = $this->setupComparisonWithQueuedChildren();
        $this->recordTerminalResult($comparison, ComparisonSide::Baseline, LabRunState::Passed, ['a' => true]);
        $this->recordTerminalResult($comparison, ComparisonSide::Candidate, LabRunState::Passed, ['a' => true]);
        app(ReconcileComparisonsService::class)->reconcile($comparison->id);
        $finished = DB::connection('pgsql')->table('comparison_runs')
            ->where('id', $comparison->id)->value('finished_at');

        // Second passage : rien ne change (idempotent).
        $second = app(ReconcileComparisonsService::class)->reconcile($comparison->id);

        $this->assertSame('already_finalized', $second->status);
        $this->assertSame(
            $finished,
            DB::connection('pgsql')->table('comparison_runs')->where('id', $comparison->id)->value('finished_at'),
        );
    }

    public function test_unknown_comparison_is_reported_not_found(): void
    {
        $report = app(ReconcileComparisonsService::class)->reconcile('00000000-0000-4000-8000-000000000bad');
        $this->assertSame('not_found', $report->status);
    }

    private function setupComparisonWithQueuedChildren(): ComparisonRun
    {
        $version = $this->publishedVersion();
        $profileId = $this->enabledComparisonProfile($version);
        $definitionId = $this->definition($version);
        $actor = User::factory()->verified()->create();
        $comparison = $this->pendingComparisonRun($profileId, $actor);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Baseline, $version);
        $this->queuedComparisonChild($comparison, $definitionId, ComparisonSide::Candidate, $version);

        return $comparison;
    }
}
