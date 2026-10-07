<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ReviewDecision;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Capsules\CapsuleVersionReview;
use App\Models\User;
use App\Policies\CapsulePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Action de revue « demander des corrections » : in_review → changes_requested.
 * Un modérateur/admin qui n'est ni owner ni contributeur enregistre une note
 * obligatoire et renseigne reviewer_id. Audit dans la même transaction.
 */
final class RequestChangesOnCapsuleVersionService
{
    public function __construct(
        private readonly CapsulePolicy $policy,
        private readonly WriteCapsuleAudit $audit,
    ) {}

    public function handle(User $actor, Capsule $capsule, CapsuleVersion $version, string $note): CapsuleVersionReview
    {
        return DB::transaction(function () use ($actor, $capsule, $version, $note): CapsuleVersionReview {
            $locked = CapsuleVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            $sourceCapsule = Capsule::whereKey($locked->capsule_id)->lockForUpdate()->firstOrFail();
            if ($sourceCapsule->id !== $capsule->id) {
                throw new AuthorizationException;
            }
            if (! $this->policy->reviewVersion($actor, $sourceCapsule, $locked->id)) {
                throw new AuthorizationException;
            }
            if ($locked->state !== CapsuleVersionState::InReview) {
                throw new AuthorizationException;
            }
            $review = CapsuleVersionReview::create([
                'version_id' => $locked->id,
                'reviewer_id' => $actor->id,
                'decision' => ReviewDecision::RequestChanges,
                'note' => $note,
                'created_at' => now()->utc(),
            ]);
            $locked->state = CapsuleVersionState::ChangesRequested;
            $locked->reviewer_id = $actor->id;
            $locked->lock_version = $locked->lock_version + 1;
            $locked->save();
            $this->audit->capsuleVersion($actor, $locked->id, 'capsule.version.changes_requested', [
                'capsule_id' => $sourceCapsule->id,
                'review_id' => $review->id,
                'lock_version' => $locked->lock_version,
            ]);

            return $review;
        });
    }
}
