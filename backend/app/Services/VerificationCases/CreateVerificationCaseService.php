<?php

declare(strict_types=1);

namespace App\Services\VerificationCases;

use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\VerificationCases\CreateVerificationCaseData;
use App\Data\VerificationCases\VerificationCaseParent;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use App\Policies\VerificationCases\VerificationCasePolicy;
use App\Services\Idempotency\IdempotencyService;
use App\Support\VerificationCases\VerificationCaseBody;
use App\Support\VerificationCases\VerificationCaseParentVisibility;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

// BV202 — crée un cas en brouillon et sa première révision éditable
// (submitted_at null). Le body canonique est haché en SHA-256 pour la
// révision ; la révision stocke le brouillon jusqu'à soumission.
final class CreateVerificationCaseService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly VerificationCasePolicy $policy,
        private readonly VerificationCaseParentVisibility $visibility,
        private readonly WriteVerificationCaseAudit $audit,
    ) {}

    public function handle(User $actor, CreateVerificationCaseData $data, IdempotencyKey $key): VerificationCase
    {
        return $this->idempotency->execute(
            $actor,
            new IdempotencyData('POST /api/v1/verification-cases', $key, $data->payload()),
            function (User $current) use ($data): void {
                if (! $this->policy->create($current)) {
                    throw new AuthorizationException;
                }
                $this->assertParentVisible($current, $data);
            },
            function (User $current) use ($data): StoredCommandResult {
                if (! $this->policy->create($current)) {
                    throw new AuthorizationException;
                }
                $this->assertParentVisible($current, $data);
                $case = (new VerificationCase)->forceFill([
                    'author_id' => $current->id,
                    'help_request_id' => $data->parentKind === VerificationCaseParent::HelpRequest ? $data->helpRequestId : null,
                    'capsule_version_id' => $data->parentKind === VerificationCaseParent::CapsuleVersion ? $data->capsuleVersionId : null,
                    'state' => VerificationCaseState::Draft,
                    'current_revision' => 0,
                    'lock_version' => 1,
                ]);
                $case->save();

                $body = VerificationCaseBody::normalize($data->body);
                $revision = (new VerificationCaseRevision)->forceFill([
                    'case_id' => $case->id,
                    'revision_no' => 1,
                    'body' => $body,
                    'digest' => VerificationCaseBody::digest($body),
                    'submitted_at' => null,
                ]);
                $revision->save();

                $this->audit->caseEvent($current, $case->id, 'verification_case.created', [
                    'parent_kind' => $data->parentKind->value,
                    'parent_id' => $case->help_request_id ?? $case->capsule_version_id,
                    'revision_no' => (int) $revision->revision_no,
                ]);

                return new StoredCommandResult(201, ['case_id' => $case->id], $case->lock_version);
            },
            function (User $current, StoredCommandResult $stored) use ($data): VerificationCase {
                if ($stored->status !== 201 || ! isset($stored->references['case_id'])) {
                    throw new IdempotencyStorageFailed('Résultat de cas mémorisé invalide.');
                }
                $case = VerificationCase::whereKey($stored->references['case_id'])->firstOrFail();
                $this->assertParentVisible($current, $data);
                if (! $this->policy->view($current, $case)) {
                    throw new AuthorizationException;
                }

                return $case->load(['author:id,handle', 'revisions' => fn ($q) => $q->orderBy('revision_no')]);
            },
        );
    }

    private function assertParentVisible(?User $current, CreateVerificationCaseData $data): void
    {
        if ($data->parentKind === VerificationCaseParent::HelpRequest) {
            /** @var string $parentId */
            $parentId = (string) Str::lower($data->helpRequestId ?? '');
            $this->visibility->assertHelpRequestVisible($current, $parentId);

            return;
        }
        /** @var string $parentId */
        $parentId = (string) Str::lower($data->capsuleVersionId ?? '');
        $this->visibility->assertCapsuleVersionVisible($current, $parentId);
    }
}
