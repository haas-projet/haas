<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Valeurs string : sources concordantes.
 * - docs/architecture/ARCHITECTURE.md:257 « CapsuleVersionState : draft, in_review, changes_requested, published, withdrawn. »
 * - HAAS_CODEX_MASTER.md:167 table « Version | draft, in_review, changes_requested, published, withdrawn ».
 *
 * Transitions citées littéralement (lot B24) :
 * - docs/execution/PLAN_COMMITS.md:263
 * - docs/execution/tasks.json:290
 * - HAAS_CODEX_MASTER.md:570
 *   « Transitions draft → in_review → changes_requested → in_review ».
 *
 * Transitions déduites, à confirmer par le relecteur :
 * - in_review → published : lot B25 (docs/execution/PLAN_COMMITS.md:273, docs/execution/tasks.json:296)
 *   « PublishCapsuleVersionService vérifie revue indépendante ».
 * - published → withdrawn : lot B31 (docs/execution/tasks.json:361-367 « retirer version »),
 *   docs/product/CAHIER_DES_CHARGES.md:502 « Le retrait d'une version demande un motif visible »,
 *   docs/quality/ACCEPTANCE_MATRIX.md:21 AC15 « Version retirée : absente des téléchargements ».
 *
 * Les retraits depuis draft, in_review et changes_requested ne figurent pas ici ; les règles
 * de retrait depuis un état non publié relèveront des Services définis par le lot B31.
 */
final class CapsuleVersionStateTest extends TestCase
{
    public function test_cases_match_documented_values_in_declared_order(): void
    {
        $values = array_map(static fn (CapsuleVersionState $case): string => $case->value, CapsuleVersionState::cases());

        $this->assertSame(['draft', 'in_review', 'changes_requested', 'published', 'withdrawn'], $values);
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_are_accepted(CapsuleVersionState $from, CapsuleVersionState $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
        $this->assertContains($to, $from->allowedTargets());
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_forbidden_transitions_are_rejected(CapsuleVersionState $from, CapsuleVersionState $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
        $this->assertNotContains($to, $from->allowedTargets());
    }

    public function test_withdrawn_is_a_terminal_state_without_outgoing_transition(): void
    {
        $this->assertSame([], CapsuleVersionState::Withdrawn->allowedTargets());
    }

    /** @return iterable<string, array{CapsuleVersionState, CapsuleVersionState}> */
    public static function allowedTransitions(): iterable
    {
        yield 'draft → in_review (B24 cité)' => [CapsuleVersionState::Draft, CapsuleVersionState::InReview];
        yield 'in_review → changes_requested (B24 cité)' => [CapsuleVersionState::InReview, CapsuleVersionState::ChangesRequested];
        yield 'changes_requested → in_review (B24 cité)' => [CapsuleVersionState::ChangesRequested, CapsuleVersionState::InReview];
        yield 'in_review → published (déduit B25, à confirmer)' => [CapsuleVersionState::InReview, CapsuleVersionState::Published];
        yield 'published → withdrawn (déduit B31/CDC:502/AC15, à confirmer)' => [CapsuleVersionState::Published, CapsuleVersionState::Withdrawn];
    }

    /** @return iterable<string, array{CapsuleVersionState, CapsuleVersionState}> */
    public static function forbiddenTransitions(): iterable
    {
        $allowed = [
            [CapsuleVersionState::Draft, CapsuleVersionState::InReview],
            [CapsuleVersionState::InReview, CapsuleVersionState::ChangesRequested],
            [CapsuleVersionState::ChangesRequested, CapsuleVersionState::InReview],
            [CapsuleVersionState::InReview, CapsuleVersionState::Published],
            [CapsuleVersionState::Published, CapsuleVersionState::Withdrawn],
        ];

        foreach (CapsuleVersionState::cases() as $from) {
            foreach (CapsuleVersionState::cases() as $to) {
                $isAllowed = false;
                foreach ($allowed as [$allowedFrom, $allowedTo]) {
                    if ($from === $allowedFrom && $to === $allowedTo) {
                        $isAllowed = true;
                        break;
                    }
                }

                if ($isAllowed) {
                    continue;
                }

                yield $from->value.' → '.$to->value => [$from, $to];
            }
        }
    }
}
