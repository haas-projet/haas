<?php

declare(strict_types=1);

namespace App\Services\VerificationCases;

use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\VerificationCases\ReviseVerificationCaseData;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Exceptions\VerificationCases\StaleVerificationCase;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use App\Policies\VerificationCases\VerificationCasePolicy;
use App\Services\Idempotency\IdempotencyService;
use App\Support\VerificationCases\VerificationCaseBody;
use App\Support\VerificationCases\VerificationCaseParentVisibility;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

// BV202 — PATCH : édite la révision en cours quand le cas est draft ;
// crée une nouvelle révision et remet le cas en draft quand il est submitted.
// Les révisions soumises précédentes et leurs digests restent intacts.
final class ReviseVerificationCaseService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly VerificationCasePolicy $policy,
        private readonly VerificationCaseParentVisibility $visibility,
        private readonly WriteVerificationCaseAudit $audit,
    ) {}

    public function handle(User $actor, string $caseId, ReviseVerificationCaseData $data, IdempotencyKey $key): VerificationCase
    {
        $route = 'PATCH /api/v1/verification-cases/'.$caseId;

        return $this->idempotency->execute(
            $actor,
            new IdempotencyData($route, $key, $data->payload()),
            function (User $current) use ($caseId): void {
                $case = $this->locked($caseId);
                if (! $this->policy->update($current, $case)) {
                    throw new AuthorizationException;
                }
                $this->assertParentVisible($current, $case);
            },
            function (User $current) use ($caseId, $data): StoredCommandResult {
                $case = $this->locked($caseId);
                $this->assertParentVisible($current, $case);
                if (! $this->policy->update($current, $case)) {
                    throw new AuthorizationException;
                }
                if (! in_array($case->state, [VerificationCaseState::Draft, VerificationCaseState::Submitted], true)) {
                    throw new StaleVerificationCase('Ce cas ne peut plus être modifié dans son état actuel.');
                }
                if ($case->lock_version !== $data->lockVersion) {
                    throw new StaleVerificationCase('Ce cas a été modifié depuis votre dernière lecture.');
                }
                $body = VerificationCaseBody::normalize($data->body);
                $digest = VerificationCaseBody::digest($body);
                if ($case->state === VerificationCaseState::Draft) {
                    $this->rewriteDraftRevision($case, $body, $digest);
                } else {
                    $this->appendDraftRevision($case, $body, $digest);
                    $case->state = VerificationCaseState::Draft;
                    $case->current_revision = 0;
                }
                $case->lock_version++;
                $case->save();
                $this->audit->caseEvent($current, $case->id, 'verification_case.revised', [
                    'from_state' => $case->getOriginal('state') instanceof VerificationCaseState
                        ? $case->getOriginal('state')->value
                        : (string) $case->getOriginal('state'),
                    'to_state' => VerificationCaseState::Draft->value,
                    'digest' => $digest,
                ]);

                return new StoredCommandResult(200, ['case_id' => $case->id], $case->lock_version);
            },
            function (User $current, StoredCommandResult $stored) use ($caseId): VerificationCase {
                if ($stored->status !== 200 || ($stored->references['case_id'] ?? null) !== $caseId) {
                    throw new IdempotencyStorageFailed('Résultat de cas mémorisé invalide.');
                }
                $case = VerificationCase::whereKey($caseId)->firstOrFail();
                if ($case->lock_version !== $stored->version) {
                    throw new StaleVerificationCase('Ce cas a changé depuis cette révision.');
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

    /** @param array{title:string,context:string,steps:string,expected:string,observed:?string,environment:?string} $body */
    private function rewriteDraftRevision(VerificationCase $case, array $body, string $digest): void
    {
        $latest = VerificationCaseRevision::where('case_id', $case->id)
            ->orderByDesc('revision_no')
            ->lockForUpdate()
            ->firstOrFail();
        if ($latest->submitted_at !== null) {
            throw new StaleVerificationCase('La dernière révision est figée ; une nouvelle doit être créée.');
        }
        $latest->forceFill([
            'body' => $body,
            'digest' => $digest,
        ])->save();
    }

    /** @param array{title:string,context:string,steps:string,expected:string,observed:?string,environment:?string} $body */
    private function appendDraftRevision(VerificationCase $case, array $body, string $digest): void
    {
        // Verrouille d'abord les lignes de révision avant de calculer le max
        // (PostgreSQL refuse FOR UPDATE combiné à MAX()).
        VerificationCaseRevision::where('case_id', $case->id)
            ->lockForUpdate()
            ->get();
        $next = (int) VerificationCaseRevision::where('case_id', $case->id)
            ->max('revision_no') + 1;
        try {
            (new VerificationCaseRevision)->forceFill([
                'case_id' => $case->id,
                'revision_no' => $next,
                'body' => $body,
                'digest' => $digest,
                'submitted_at' => null,
            ])->save();
        } catch (QueryException $error) {
            if (($error->errorInfo[0] ?? null) === '23505') {
                throw new StaleVerificationCase('Une révision concurrente a été écrite pour ce cas. Rechargez-le.');
            }
            throw $error;
        }
    }
}
