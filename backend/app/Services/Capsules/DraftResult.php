<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Data\Idempotency\StoredCommandResult;
use App\Enums\Capsules\CapsuleVersionState;
use App\Exceptions\Capsules\CapsuleDraftConflict;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Policies\CapsulePolicy;
use Illuminate\Auth\Access\AuthorizationException;

final class DraftResult
{
    /** @return array{stored: StoredCommandResult, capsule: Capsule, version: CapsuleVersion} */
    public function check(User $actor, StoredCommandResult $stored): array
    {
        $capsule = Capsule::whereKey($stored->references['capsule_id'])->lockForUpdate()->firstOrFail();
        $version = CapsuleVersion::whereKey($stored->references['version_id'])->where('capsule_id', $capsule->id)->lockForUpdate()->firstOrFail();
        if (! (new CapsulePolicy)->createVersionDraft($actor, $capsule)) {
            throw new AuthorizationException;
        }
        if ($version->state !== CapsuleVersionState::Draft || $version->lock_version !== $stored->version) {
            throw new CapsuleDraftConflict('Le brouillon a changé. Rechargez sa version actuelle.');
        }

        $version->load('technologies');
        $capsule->setRelation('draftVersion', $version);

        return ['stored' => $stored, 'capsule' => $capsule, 'version' => $version];
    }
}
