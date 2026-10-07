<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Data\Capsules\UpdateDraftData;
use App\Exceptions\Capsules\StaleCapsuleVersion;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Policies\CapsulePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Édition d'un brouillon de capsule : transaction PostgreSQL, lockForUpdate,
 * vérification du lock_version, incrémentation + audit, le tout atomique.
 * Les champs state/owner/reviewer/published_at/version_label ne sont jamais
 * touchés ici (c'est l'affaire des Services B24/B25/B31).
 */
final class UpdateCapsuleVersionDraftService
{
    public function __construct(
        private readonly CapsulePolicy $policy,
        private readonly WriteCapsuleAudit $audit,
    ) {}

    public function handle(User $actor, Capsule $capsule, CapsuleVersion $version, UpdateDraftData $data): CapsuleVersion
    {
        if (! $data->hasAnyChange()) {
            throw new \InvalidArgumentException('Aucune modification soumise.');
        }

        return DB::transaction(function () use ($actor, $capsule, $version, $data): CapsuleVersion {
            $locked = CapsuleVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            $sourceCapsule = Capsule::whereKey($locked->capsule_id)->lockForUpdate()->firstOrFail();
            if ($sourceCapsule->id !== $capsule->id) {
                throw new AuthorizationException;
            }
            if (! $this->policy->editDraft($actor, $sourceCapsule, $locked->state, $locked->id)) {
                throw new AuthorizationException;
            }
            if ($locked->lock_version !== $data->lockVersion) {
                throw new StaleCapsuleVersion('Cette version a été modifiée. Rechargez sa dernière forme.');
            }
            $changes = [];
            if ($data->body !== null) {
                $locked->body = $data->body;
                $changes[] = 'body';
            }
            if ($data->limits !== null) {
                $locked->limits = $data->limits;
                $changes[] = 'limits';
            }
            $locked->lock_version = $locked->lock_version + 1;
            $locked->save();
            if ($data->technologies !== null) {
                $sync = [];
                foreach ($data->technologies as $attachment) {
                    $sync[$attachment->technologyId] = ['version_label' => $attachment->versionLabel];
                }
                $locked->technologies()->sync($sync);
                $changes[] = 'technologies';
            }
            $this->audit->capsuleVersion($actor, $locked->id, 'capsule.version.draft.updated', [
                'capsule_id' => $capsule->id,
                'lock_version' => $locked->lock_version,
                'changed_fields' => $changes,
            ]);

            return $locked->refresh();
        });
    }
}
