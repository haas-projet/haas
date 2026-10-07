<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Exceptions\Capsules\InsufficientDraftContent;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Policies\CapsulePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Soumet une version-brouillon à la revue (transitions draft → in_review et
 * changes_requested → in_review). Audit dans la même transaction.
 */
final class SubmitCapsuleVersionForReviewService
{
    public function __construct(
        private readonly CapsulePolicy $policy,
        private readonly WriteCapsuleAudit $audit,
    ) {}

    public function handle(User $actor, Capsule $capsule, CapsuleVersion $version): CapsuleVersion
    {
        return DB::transaction(function () use ($actor, $capsule, $version): CapsuleVersion {
            $locked = CapsuleVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            $sourceCapsule = Capsule::whereKey($locked->capsule_id)->lockForUpdate()->firstOrFail();
            if ($sourceCapsule->id !== $capsule->id) {
                throw new AuthorizationException;
            }
            if (! $this->policy->submitForReview($actor, $sourceCapsule, $locked->state, $locked->id)) {
                throw new AuthorizationException;
            }
            $missing = $this->missingContent($locked);
            if ($missing !== []) {
                throw new InsufficientDraftContent(implode('|', $missing));
            }
            $locked->state = CapsuleVersionState::InReview;
            $locked->lock_version = $locked->lock_version + 1;
            $locked->save();
            $this->audit->capsuleVersion($actor, $locked->id, 'capsule.version.submitted_for_review', [
                'capsule_id' => $sourceCapsule->id,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->refresh();
        });
    }

    /** @return list<string> */
    private function missingContent(CapsuleVersion $version): array
    {
        $missing = [];
        if (trim($version->body) === '' || strlen(trim($version->body)) < 20) {
            $missing[] = 'body';
        }
        if ($version->limits === null || trim($version->limits) === '') {
            $missing[] = 'limits';
        }

        return $missing;
    }
}
