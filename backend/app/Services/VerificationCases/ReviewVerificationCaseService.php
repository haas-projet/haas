<?php

declare(strict_types=1);

namespace App\Services\VerificationCases;

use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\VerificationCases\ReviewVerificationCaseData;
use App\Enums\VerificationCases\ReviewDecision;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Exceptions\VerificationCases\StaleVerificationCase;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Policies\VerificationCases\VerificationCasePolicy;
use App\Services\Idempotency\IdempotencyService;
use Illuminate\Auth\Access\AuthorizationException;

// BV203 — revue par un modérateur/admin tiers. Transition submitted → reviewed
// (ou declined avec motif). Transaction, verrou, audit dans la même
// transaction. L'intégration (integrated) n'est jamais faite par cette route :
// elle revient à la commande d'intégration lab:bind-case.
final class ReviewVerificationCaseService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly VerificationCasePolicy $policy,
        private readonly WriteVerificationCaseAudit $audit,
    ) {}

    public function handle(User $actor, string $caseId, ReviewVerificationCaseData $data, IdempotencyKey $key): VerificationCase
    {
        $route = 'POST /api/v1/admin/verification-cases/'.$caseId.'/review';

        return $this->idempotency->execute(
            $actor,
            new IdempotencyData($route, $key, $data->payload()),
            function (User $current) use ($caseId): void {
                $case = $this->locked($caseId);
                if (! $this->policy->review($current, $case)) {
                    throw new AuthorizationException;
                }
            },
            function (User $current) use ($caseId, $data): StoredCommandResult {
                $case = $this->locked($caseId);
                if (! $this->policy->review($current, $case)) {
                    throw new AuthorizationException;
                }
                if ($case->lock_version !== $data->lockVersion) {
                    throw new StaleVerificationCase('Ce cas a été modifié depuis votre dernière lecture.');
                }
                if ($case->state !== VerificationCaseState::Submitted) {
                    throw new StaleVerificationCase('Seul un cas soumis peut être revu.');
                }
                $case->state = $data->decision === ReviewDecision::Reviewed
                    ? VerificationCaseState::Reviewed
                    : VerificationCaseState::Declined;
                $case->lock_version++;
                $case->save();
                $this->audit->caseEvent($current, $case->id, 'verification_case.'.$data->decision->value, [
                    'reviewer_id' => $current->id,
                    'reason' => $data->reason,
                    'revision_no' => (int) $case->current_revision,
                ]);

                return new StoredCommandResult(200, ['case_id' => $case->id], $case->lock_version);
            },
            function (User $current, StoredCommandResult $stored) use ($caseId): VerificationCase {
                if ($stored->status !== 200 || ($stored->references['case_id'] ?? null) !== $caseId) {
                    throw new IdempotencyStorageFailed('Résultat de cas mémorisé invalide.');
                }
                $case = VerificationCase::whereKey($caseId)->firstOrFail();
                if ($case->lock_version !== $stored->version) {
                    throw new StaleVerificationCase('Ce cas a changé depuis cette revue.');
                }

                return $case->load(['author:id,handle', 'revisions' => fn ($q) => $q->orderBy('revision_no')]);
            },
        );
    }

    private function locked(string $caseId): VerificationCase
    {
        return VerificationCase::whereKey($caseId)->lockForUpdate()->firstOrFail();
    }
}
