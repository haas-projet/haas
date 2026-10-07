<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Data\Capsules\TechnologyAttachmentData;
use App\Data\Capsules\VersionDraftData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ContributionRole;
use App\Exceptions\Capsules\CapsuleDraftConflict;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Policies\CapsulePolicy;
use App\Services\Idempotency\IdempotencyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ajoute une nouvelle version-brouillon à une capsule existante. L'unicité
 * (capsule_id, version_label) est appliquée en base ; une collision remonte
 * en 409 via CapsuleDraftConflict et son traitement dans le contrôleur.
 */
final class CreateCapsuleVersionDraftService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly CapsulePolicy $policy,
        private readonly WriteCapsuleAudit $audit,
    ) {}

    /** @return array{version_id: string, version: CapsuleVersion} */
    public function handle(User $actor, Capsule $capsule, VersionDraftData $draft, IdempotencyKey $key): array
    {
        $target = 'POST /api/v1/capsules/'.$capsule->id.'/versions';
        $payload = $this->canonicalize($draft);
        $idempotent = new IdempotencyData($target, $key, $payload);
        $result = $this->idempotency->execute(
            $actor,
            $idempotent,
            function (User $a) use ($capsule): void {
                $currentCapsule = Capsule::whereKey($capsule->id)->lockForUpdate()->firstOrFail();
                if (! $this->policy->createVersionDraft($a, $currentCapsule)) {
                    throw new AuthorizationException;
                }
            },
            fn (User $a) => $this->writeNewVersion($a, $capsule, $draft),
            fn (User $a, StoredCommandResult $stored) => (new DraftResult)->check($a, $stored),
        );

        return [
            'version_id' => $result['stored']->references['version_id'],
            'version' => $result['version'],
        ];
    }

    private function writeNewVersion(User $actor, Capsule $capsule, VersionDraftData $draft): StoredCommandResult
    {
        (new CheckDraftTechnologies)->check($draft->technologies);
        if (CapsuleVersion::where('capsule_id', $capsule->id)->where('version_label', $draft->versionLabel)->exists()) {
            throw new CapsuleDraftConflict('Cette version existe déjà pour la capsule.');
        }
        $version = (new CapsuleVersion)->forceFill([
            'capsule_id' => $capsule->id,
            'version_label' => $draft->versionLabel,
            'body' => $draft->body,
            'limits' => $draft->limits,
            'state' => CapsuleVersionState::Draft,
        ]);
        $version->save();
        $this->attachTechnologies($version, $draft);
        DB::table('capsule_contributors')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => $version->id,
            'user_id' => $actor->id,
            'contribution_role' => ContributionRole::Diagnosis->value,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        $this->audit->capsuleVersion($actor, $version->id, 'capsule.version.draft.created', [
            'capsule_id' => $capsule->id,
            'version_label' => $version->version_label,
        ]);

        return new StoredCommandResult(
            status: 201,
            references: ['capsule_id' => $capsule->id, 'version_id' => $version->id],
            version: 1,
        );
    }

    private function attachTechnologies(CapsuleVersion $version, VersionDraftData $draft): void
    {
        if ($draft->technologies === []) {
            return;
        }
        $sync = [];
        foreach ($draft->technologies as $attachment) {
            $sync[$attachment->technologyId] = ['version_label' => $attachment->versionLabel];
        }
        $version->technologies()->attach($sync);
    }

    /** @return array<array-key, mixed> */
    private function canonicalize(VersionDraftData $draft): array
    {
        return [
            'version_label' => $draft->versionLabel,
            'body' => $draft->body,
            'limits' => $draft->limits,
            'technologies' => array_map(static fn (TechnologyAttachmentData $a) => [
                'technology_id' => $a->technologyId,
                'version_label' => $a->versionLabel,
            ], $draft->technologies),
        ];
    }
}
