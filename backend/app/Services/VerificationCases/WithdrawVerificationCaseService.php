<?php

declare(strict_types=1);

namespace App\Services\VerificationCases;

use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\VerificationCases\WithdrawVerificationCaseData;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Exceptions\VerificationCases\StaleVerificationCase;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Policies\VerificationCases\VerificationCasePolicy;
use App\Services\Idempotency\IdempotencyService;
use App\Support\VerificationCases\VerificationCaseParentVisibility;
use Illuminate\Auth\Access\AuthorizationException;

// BV202 — retrait par l'auteur depuis draft ou submitted uniquement. Motif
// obligatoire. L'historique des révisions reste en table ; l'état bascule.
final class WithdrawVerificationCaseService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly VerificationCasePolicy $policy,
        private readonly VerificationCaseParentVisibility $visibility,
        private readonly WriteVerificationCaseAudit $audit,
    ) {}

    public function handle(User $actor, string $caseId, WithdrawVerificationCaseData $data, IdempotencyKey $key): VerificationCase
    {
        $route = 'POST /api/v1/verification-cases/'.$caseId.'/withdraw';

        return $this->idempotency->execute(
            $actor,
            new IdempotencyData($route, $key, $data->payload()),
            function (User $current) use ($caseId): void {
                $case = $this->locked($caseId);
                if (! $this->policy->withdraw($current, $case)) {
                    throw new AuthorizationException;
                }
                $this->assertParentVisible($current, $case);
            },
            function (User $current) use ($caseId, $data): StoredCommandResult {
                $case = $this->locked($caseId);
                if (! $this->policy->withdraw($current, $case)) {
                    throw new AuthorizationException;
                }
                $this->assertParentVisible($current, $case);
                if ($case->lock_version !== $data->lockVersion) {
                    throw new StaleVerificationCase('Ce cas a été modifié depuis votre dernière lecture.');
                }
                if (! in_array($case->state, [VerificationCaseState::Draft, VerificationCaseState::Submitted], true)) {
                    throw new StaleVerificationCase('Ce cas ne peut plus être retiré dans son état actuel.');
                }
                // La trace du retrait et son motif sont dans l'audit (B12),
                // pas sur la table du cas : seule la ligne d'état change ici.
                // Pour franchir le CHECK verification_cases_current_revision_consistent
                // (withdrawn n'est pas draft), on garantit current_revision >= 1 en
                // alignant sur la dernière révision connue si besoin.
                $from = $case->state;
                $case->state = VerificationCaseState::Withdrawn;
                if ($case->current_revision < 1) {
                    $maxRevision = (int) $case->revisions()->max('revision_no');
                    $case->current_revision = max(1, $maxRevision);
                }
                $case->lock_version++;
                $case->save();
                $this->audit->caseEvent($current, $case->id, 'verification_case.withdrawn', [
                    'from_state' => $from->value,
                    'reason' => $data->reason,
                ]);

                return new StoredCommandResult(200, ['case_id' => $case->id], $case->lock_version);
            },
            function (User $current, StoredCommandResult $stored) use ($caseId): VerificationCase {
                if ($stored->status !== 200 || ($stored->references['case_id'] ?? null) !== $caseId) {
                    throw new IdempotencyStorageFailed('Résultat de cas mémorisé invalide.');
                }
                $case = VerificationCase::whereKey($caseId)->firstOrFail();
                if ($case->lock_version !== $stored->version) {
                    throw new StaleVerificationCase('Ce cas a changé depuis ce retrait.');
                }

                return $case->load(['author:id,handle', 'revisions' => fn ($q) => $q->orderBy('revision_no')]);
            },
        );
    }

    private function locked(string $caseId): VerificationCase
    {
        return VerificationCase::whereKey($caseId)->lockForUpdate()->firstOrFail();
    }

    private function assertParentVisible(?User $current, VerificationCase $case): void
    {
        if ($case->help_request_id !== null) {
            $this->visibility->assertHelpRequestVisible($current, $case->help_request_id);

            return;
        }
        if ($case->capsule_version_id !== null) {
            $this->visibility->assertCapsuleVersionVisible($current, $case->capsule_version_id);
        }
    }
}
