<?php

declare(strict_types=1);

namespace Tests\Unit\Comparisons;

use App\Enums\Comparisons\ComparisonOutcome;
use App\Enums\Comparisons\ComparisonState;
use App\Enums\Lab\LabRunState;
use App\Services\Comparisons\ComparisonOutcomeCalculator;
use PHPUnit\Framework\TestCase;

final class ComparisonOutcomeCalculatorTest extends TestCase
{
    public function test_running_global_state_is_not_short_circuited_only_error_and_timed_out_are(): void
    {
        // La spec (CAHIER §44 ligne 1655) ne liste que error/timed_out comme
        // états « globaux » dégénérés ; running n'est pas court-circuité ici,
        // le réconciliateur ne l'appelle de toute façon que lorsque les deux
        // enfants sont terminaux.
        $calc = new ComparisonOutcomeCalculator;
        $outcome = $calc->calculate(
            ['a' => true], ['a' => true],
            ComparisonState::Running,
            LabRunState::Passed, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Unchanged, $outcome);
    }

    public function test_global_timed_out_is_inconclusive(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            ['a' => true], ['a' => true],
            ComparisonState::TimedOut,
            LabRunState::Passed, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Inconclusive, $outcome);
    }

    public function test_global_error_is_inconclusive(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            ['a' => true], ['a' => true],
            ComparisonState::Error,
            LabRunState::Passed, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Inconclusive, $outcome);
    }

    public function test_child_in_error_is_inconclusive(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            null, ['a' => true],
            ComparisonState::Completed,
            LabRunState::Error, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Inconclusive, $outcome);
    }

    public function test_child_timed_out_is_inconclusive(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            ['a' => true], null,
            ComparisonState::Completed,
            LabRunState::Passed, LabRunState::TimedOut,
        );
        $this->assertSame(ComparisonOutcome::Inconclusive, $outcome);
    }

    public function test_child_still_queued_or_running_is_inconclusive(): void
    {
        $calc = new ComparisonOutcomeCalculator;
        $this->assertSame(ComparisonOutcome::Inconclusive, $calc->calculate(
            ['a' => true], null,
            ComparisonState::Completed,
            LabRunState::Passed, LabRunState::Queued,
        ));
        $this->assertSame(ComparisonOutcome::Inconclusive, $calc->calculate(
            ['a' => true], null,
            ComparisonState::Completed,
            LabRunState::Passed, LabRunState::Running,
        ));
    }

    public function test_null_child_state_is_inconclusive(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            ['a' => true], ['a' => true],
            ComparisonState::Completed,
            null, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Inconclusive, $outcome);
    }

    public function test_missing_result_is_inconclusive(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            null, ['a' => true],
            ComparisonState::Completed,
            LabRunState::Passed, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Inconclusive, $outcome);
    }

    public function test_empty_result_is_inconclusive(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            [], ['a' => true],
            ComparisonState::Completed,
            LabRunState::Passed, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Inconclusive, $outcome);
    }

    public function test_different_assertion_identifier_sets_are_inconclusive_never_improved(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            ['a' => false, 'b' => true],
            ['a' => true, 'c' => true],
            ComparisonState::Completed,
            LabRunState::Failed, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Inconclusive, $outcome);
    }

    public function test_key_order_is_irrelevant(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            ['beta' => true, 'alpha' => false],
            ['alpha' => true, 'beta' => true],
            ComparisonState::Completed,
            LabRunState::Failed, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Improved, $outcome);
    }

    public function test_improved_requires_at_least_one_failure_to_success_and_no_regression(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            ['a' => false, 'b' => true],
            ['a' => true, 'b' => true],
            ComparisonState::Completed,
            LabRunState::Failed, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Improved, $outcome);
    }

    public function test_regressed_requires_at_least_one_success_to_failure_and_no_improvement(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            ['a' => true, 'b' => true],
            ['a' => false, 'b' => true],
            ComparisonState::Completed,
            LabRunState::Passed, LabRunState::Failed,
        );
        $this->assertSame(ComparisonOutcome::Regressed, $outcome);
    }

    public function test_mixed_combines_improvement_and_regression(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            ['a' => false, 'b' => true],
            ['a' => true, 'b' => false],
            ComparisonState::Completed,
            LabRunState::Failed, LabRunState::Failed,
        );
        $this->assertSame(ComparisonOutcome::Mixed, $outcome);
    }

    public function test_unchanged_two_identical_failures_on_same_assertions(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            ['a' => false, 'b' => false],
            ['a' => false, 'b' => false],
            ComparisonState::Completed,
            LabRunState::Failed, LabRunState::Failed,
        );
        $this->assertSame(ComparisonOutcome::Unchanged, $outcome);
    }

    public function test_unchanged_two_identical_successes(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            ['a' => true, 'b' => true],
            ['a' => true, 'b' => true],
            ComparisonState::Completed,
            LabRunState::Passed, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Unchanged, $outcome);
    }

    public function test_candidate_never_wins_by_default_when_baseline_is_missing(): void
    {
        $outcome = (new ComparisonOutcomeCalculator)->calculate(
            null, ['a' => true, 'b' => true],
            ComparisonState::Completed,
            LabRunState::Passed, LabRunState::Passed,
        );
        $this->assertSame(ComparisonOutcome::Inconclusive, $outcome);
    }

    public function test_determinism_same_inputs_yield_same_outcome(): void
    {
        $calc = new ComparisonOutcomeCalculator;
        $first = $calc->calculate(
            ['a' => false], ['a' => true],
            ComparisonState::Completed,
            LabRunState::Failed, LabRunState::Passed,
        );
        $second = $calc->calculate(
            ['a' => false], ['a' => true],
            ComparisonState::Completed,
            LabRunState::Failed, LabRunState::Passed,
        );
        $this->assertSame($first, $second);
        $this->assertSame(ComparisonOutcome::Improved, $first);
    }

    public function test_expose_completed_returns_the_persisted_column(): void
    {
        $calc = new ComparisonOutcomeCalculator;
        $this->assertSame(ComparisonOutcome::Improved, $calc->expose(ComparisonState::Completed, ComparisonOutcome::Improved));
        $this->assertSame(ComparisonOutcome::Inconclusive, $calc->expose(ComparisonState::Completed, ComparisonOutcome::Inconclusive));
        $this->assertNull($calc->expose(ComparisonState::Completed, null));
    }

    public function test_expose_error_and_timed_out_derive_inconclusive_though_column_is_null(): void
    {
        $calc = new ComparisonOutcomeCalculator;
        $this->assertSame(ComparisonOutcome::Inconclusive, $calc->expose(ComparisonState::Error, null));
        $this->assertSame(ComparisonOutcome::Inconclusive, $calc->expose(ComparisonState::TimedOut, null));
    }

    public function test_expose_queued_and_running_return_null(): void
    {
        $calc = new ComparisonOutcomeCalculator;
        $this->assertNull($calc->expose(ComparisonState::Queued, null));
        $this->assertNull($calc->expose(ComparisonState::Running, null));
    }
}
