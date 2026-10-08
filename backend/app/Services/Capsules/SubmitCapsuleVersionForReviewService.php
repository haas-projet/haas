<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Data\Capsules\ReviewCommandData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Enums\Capsules\CapsuleVersionState;
use App\Exceptions\Capsules\InsufficientDraftContent;
use App\Exceptions\Capsules\StaleCapsuleVersion;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Policies\CapsulePolicy;
use App\Services\Idempotency\IdempotencyService;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Soumet une version-brouillon à la revue (transitions draft → in_review et
 * changes_requested → in_review). Audit dans la même transaction.
 */
final class SubmitCapsuleVersionForReviewService
{
    public function __construct(
        private readonly CapsulePolicy $policy,
        private readonly WriteCapsuleAudit $audit,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function handle(User $actor, Capsule $capsule, CapsuleVersion $version, ReviewCommandData $command, IdempotencyKey $key): CapsuleVersion
    {
        return $this->idempotency->execute($actor,
            new IdempotencyData('POST /api/v1/capsules/'.$capsule->id.'/versions/'.$version->id.'/submit-review', $key, ['lock_version' => $command->lockVersion]),
            function (User $current) use ($capsule, $version): void {
                $sourceCapsule = Capsule::whereKey($capsule->id)->lockForUpdate()->firstOrFail();
                $locked = CapsuleVersion::whereKey($version->id)->where('capsule_id', $sourceCapsule->id)->lockForUpdate()->firstOrFail();
                if (! $this->policy->editDraft($current, $sourceCapsule, CapsuleVersionState::Draft, $locked->id)) {
                    throw new AuthorizationException;
                }
            },
            function (User $current) use ($capsule, $version, $command): StoredCommandResult {
                $sourceCapsule = Capsule::whereKey($capsule->id)->firstOrFail();
                $locked = CapsuleVersion::whereKey($version->id)->where('capsule_id', $sourceCapsule->id)->firstOrFail();
                if ($locked->lock_version !== $command->lockVersion) {
                    throw new StaleCapsuleVersion('Cette version a été modifiée. Rechargez sa dernière forme.');
                }
                if (! $this->policy->submitForReview($current, $sourceCapsule, $locked->state, $locked->id)) {
                    throw new AuthorizationException;
                }
                $missing = $this->missingContent($locked);
                if ($missing !== []) {
                    throw new InsufficientDraftContent(implode('|', $missing));
                }
                $locked->state = CapsuleVersionState::InReview;
                $locked->reviewer_id = null;
                $locked->lock_version = $locked->lock_version + 1;
                $locked->save();
                $this->audit->capsuleVersion($current, $locked->id, 'capsule.version.submitted_for_review', [
                    'capsule_id' => $sourceCapsule->id,
                    'lock_version' => $locked->lock_version,
                ]);

                return new StoredCommandResult(200, ['capsule_id' => $sourceCapsule->id, 'version_id' => $locked->id], $locked->lock_version);
            }, function (User $current, StoredCommandResult $stored): CapsuleVersion {
                $locked = CapsuleVersion::whereKey($stored->references['version_id'])->firstOrFail();
                if ($locked->state !== CapsuleVersionState::InReview || $locked->lock_version !== $stored->version) {
                    throw new StaleCapsuleVersion('La version a changé depuis cette soumission.');
                }

                return $locked->load('technologies');
            });
    }

    /** @return list<string> */
    private function missingContent(CapsuleVersion $version): array
    {
        $missing = [];
        if (trim($version->body) === '' || mb_strlen(trim($version->body)) < 20) {
            $missing[] = 'body';
        }
        if ($version->limits === null || trim($version->limits) === '') {
            $missing[] = 'limits';
        }

        return $missing;
    }
}
