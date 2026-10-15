<?php

declare(strict_types=1);

namespace App\Services\VerificationCases;

use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\VerificationCases\SubmitVerificationCaseData;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Exceptions\VerificationCases\StaleVerificationCase;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use App\Policies\VerificationCases\VerificationCasePolicy;
use App\Services\Idempotency\IdempotencyService;
use App\Support\VerificationCases\VerificationCaseParentVisibility;
use Illuminate\Auth\Access\AuthorizationException;

// BV202 — fige la révision brouillon courante (submitted_at = now) et porte
// le cas en submitted. current_revision prend la valeur de la révision figée.
final class SubmitVerificationCaseService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly VerificationCasePolicy $policy,
        private readonly VerificationCaseParentVisibility $visibility,
        private readonly WriteVerificationCaseAudit $audit,
    ) {}

    public function handle(User $actor, string $caseId, SubmitVerificationCaseData $data, IdempotencyKey $key): VerificationCase
    {
        $route = 'POST /api/v1/verification-cases/'.$caseId.'/submit';

        return $this->idempotency->execute(
            $actor,
            new IdempotencyData($route, $key, $data->payload()),
            function (User $current) use ($caseId): void {
                $case = $this->locked($caseId);
                if (! $this->policy->submit($current, $case)) {
                    throw new AuthorizationException;
                }
                $this->assertParentVisible($current, $case);
            },
            function (User $current) use ($caseId, $data): StoredCommandResult {
                $case = $this->locked($caseId);
                if (! $this->policy->submit($current, $case)) {
                    throw new AuthorizationException;
                }
                $this->assertParentVisible($current, $case);
                if ($case->lock_version !== $data->lockVersion) {
                    throw new StaleVerificationCase('Ce cas a été modifié depuis votre dernière lecture.');
                }
                if ($case->state !== VerificationCaseState::Draft) {
                    throw new StaleVerificationCase('Seul un brouillon peut être soumis.');
                }
                $latest = VerificationCaseRevision::where('case_id', $case->id)
                    ->orderByDesc('revision_no')
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($latest->submitted_at !== null) {
                    throw new StaleVerificationCase('Cette révision est déjà figée.');
                }
                $submittedAt = now()->utc();
                $latest->forceFill(['submitted_at' => $submittedAt])->save();
                $case->state = VerificationCaseState::Submitted;
                $case->current_revision = (int) $latest->revision_no;
                $case->lock_version++;
                $case->save();
                $this->audit->caseEvent($current, $case->id, 'verification_case.submitted', [
                    'revision_no' => (int) $latest->revision_no,
                    'digest' => (string) $latest->digest,
                ]);

                return new StoredCommandResult(200, ['case_id' => $case->id], $case->lock_version);
            },
            function (User $current, StoredCommandResult $stored) use ($caseId): VerificationCase {
                if ($stored->status !== 200 || ($stored->references['case_id'] ?? null) !== $caseId) {
                    throw new IdempotencyStorageFailed('Résultat de cas mémorisé invalide.');
                }
                $case = VerificationCase::whereKey($caseId)->firstOrFail();
                if ($case->lock_version !== $stored->version) {
                    throw new StaleVerificationCase('Ce cas a changé depuis cette soumission.');
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
