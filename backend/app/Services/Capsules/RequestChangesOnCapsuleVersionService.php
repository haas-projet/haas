<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Data\Capsules\ReviewCommandData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\Notifications\NotificationEvent;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ReviewDecision;
use App\Exceptions\Capsules\StaleCapsuleVersion;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Capsules\CapsuleVersionReview;
use App\Models\User;
use App\Policies\CapsulePolicy;
use App\Services\Idempotency\IdempotencyService;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Auth\Access\AuthorizationException;

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
        private readonly IdempotencyService $idempotency,
        private readonly NotificationOutbox $outbox,
    ) {}

    public function handle(User $actor, Capsule $capsule, CapsuleVersion $version, ReviewCommandData $command, IdempotencyKey $key): CapsuleVersionReview
    {
        if ($command->note === null) {
            throw new \InvalidArgumentException('La note de revue est obligatoire.');
        }

        return $this->idempotency->execute($actor,
            new IdempotencyData('POST /api/v1/admin/capsules/'.$capsule->id.'/versions/'.$version->id.'/request-changes', $key, ['lock_version' => $command->lockVersion, 'note' => $command->note]),
            function (User $current) use ($capsule, $version): void {
                $sourceCapsule = Capsule::whereKey($capsule->id)->lockForUpdate()->firstOrFail();
                $locked = CapsuleVersion::whereKey($version->id)->where('capsule_id', $sourceCapsule->id)->lockForUpdate()->firstOrFail();
                if (! $this->policy->reviewVersion($current, $sourceCapsule, $locked->id)) {
                    throw new AuthorizationException;
                }
            },
            function (User $current) use ($capsule, $version, $command): StoredCommandResult {
                $sourceCapsule = Capsule::whereKey($capsule->id)->firstOrFail();
                $locked = CapsuleVersion::whereKey($version->id)->where('capsule_id', $sourceCapsule->id)->firstOrFail();
                if (! $this->policy->reviewVersion($current, $sourceCapsule, $locked->id)) {
                    throw new AuthorizationException;
                }
                if ($locked->lock_version !== $command->lockVersion) {
                    throw new StaleCapsuleVersion('Cette version a été modifiée. Rechargez sa dernière forme.');
                }
                if ($locked->state !== CapsuleVersionState::InReview) {
                    throw new AuthorizationException;
                }
                $review = (new CapsuleVersionReview)->forceFill([
                    'version_id' => $locked->id,
                    'reviewer_id' => $current->id,
                    'decision' => ReviewDecision::RequestChanges,
                    'note' => $command->note,
                    'reviewed_lock_version' => $locked->lock_version,
                    'created_at' => now()->utc(),
                ]);
                $review->save();
                $locked->state = CapsuleVersionState::ChangesRequested;
                $locked->reviewer_id = $current->id;
                $locked->lock_version = $locked->lock_version + 1;
                $locked->save();
                $this->audit->capsuleVersion($current, $locked->id, 'capsule.version.changes_requested', [
                    'capsule_id' => $sourceCapsule->id,
                    'review_id' => $review->id,
                    'lock_version' => $locked->lock_version,
                ]);
                $this->outbox->record(new NotificationEvent($review->id, $sourceCapsule->owner_id, 'capsule.review.changes_requested'));

                return new StoredCommandResult(201, ['capsule_id' => $sourceCapsule->id, 'version_id' => $locked->id, 'review_id' => $review->id], $locked->lock_version);
            }, function (User $current, StoredCommandResult $stored): CapsuleVersionReview {
                $locked = CapsuleVersion::whereKey($stored->references['version_id'])->firstOrFail();
                if ($locked->state !== CapsuleVersionState::ChangesRequested || $locked->lock_version !== $stored->version) {
                    throw new StaleCapsuleVersion('La version a changé depuis cette revue.');
                }

                return CapsuleVersionReview::whereKey($stored->references['review_id'])->where('version_id', $locked->id)->where('reviewer_id', $current->id)->firstOrFail();
            });
    }
}
