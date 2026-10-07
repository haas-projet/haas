<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Data\Capsules\CapsuleDraftData;
use App\Data\Capsules\VersionDraftData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Enums\Capsules\CapsuleSourceKind;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ContributionRole;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\HelpRequest;
use App\Models\User;
use App\Policies\CapsulePolicy;
use App\Services\Idempotency\IdempotencyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crée une capsule et sa première version-brouillon dans une transaction
 * unique. L'audit est écrit dans la même transaction : un rollback annule
 * tout (table capsules + capsule_versions + capsule_version_technologies
 * + capsule_contributors + content_revisions).
 */
final class CreateCapsuleDraftService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly CapsulePolicy $policy,
        private readonly WriteCapsuleAudit $audit,
    ) {}

    /** @return array{capsule_id: string, version_id: string} */
    public function handle(User $actor, CapsuleDraftData $data, IdempotencyKey $key): array
    {
        $target = 'POST /api/v1/capsules';
        $payload = $this->canonicalize($data);
        $idempotent = new IdempotencyData($target, $key, $payload);
        $result = $this->idempotency->execute(
            $actor,
            $idempotent,
            fn (User $a) => $this->authorize($a, $data),
            fn (User $a) => $this->writeNewCapsule($a, $data),
            fn (User $a, StoredCommandResult $stored) => $stored,
        );

        return [
            'capsule_id' => $result->references['capsule_id'],
            'version_id' => $result->references['version_id'],
        ];
    }

    private function authorize(User $actor, CapsuleDraftData $data): void
    {
        if ($data->sourceKind === CapsuleSourceKind::Editorial) {
            if (! $this->policy->proposeEditorial($actor)) {
                throw new AuthorizationException;
            }

            return;
        }
        $request = HelpRequest::whereKey($data->sourceRequestId)->first();
        if ($request === null) {
            throw ValidationException::withMessages(['source_request_id' => ['Demande source introuvable.']]);
        }
        if (! $this->policy->proposeFromHelpRequest($actor, $request)) {
            throw new AuthorizationException;
        }
        $resolutionActive = DB::table('resolutions')
            ->where('request_id', $request->id)
            ->whereNull('revoked_at')
            ->exists();
        if (! $resolutionActive) {
            throw ValidationException::withMessages(['source_request_id' => ['La demande source doit porter une résolution active.']]);
        }
    }

    private function writeNewCapsule(User $actor, CapsuleDraftData $data): StoredCommandResult
    {
        $capsule = Capsule::create([
            'slug' => $data->slug,
            'source_request_id' => $data->sourceRequestId,
            'owner_id' => $actor->id,
            'editorial_origin' => $data->editorialOrigin,
        ]);
        $version = CapsuleVersion::create([
            'capsule_id' => $capsule->id,
            'version_label' => $data->version->versionLabel,
            'body' => $data->version->body,
            'limits' => $data->version->limits,
            'state' => CapsuleVersionState::Draft,
        ]);
        $this->attachTechnologies($version, $data->version);
        $this->attachAuthor($version->id, $actor->id);
        $this->audit->capsuleVersion($actor, $version->id, 'capsule.draft.created', [
            'capsule_id' => $capsule->id,
            'version_label' => $version->version_label,
            'source_kind' => $data->sourceKind->value,
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

    private function attachAuthor(string $versionId, string $userId): void
    {
        DB::table('capsule_contributors')->insert([
            'id' => (string) Str::uuid(),
            'version_id' => $versionId,
            'user_id' => $userId,
            'contribution_role' => ContributionRole::Diagnosis->value,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
    }

    /** @return array<array-key, mixed> */
    private function canonicalize(CapsuleDraftData $data): array
    {
        $technologies = [];
        foreach ($data->version->technologies as $attachment) {
            $technologies[] = [
                'technology_id' => $attachment->technologyId,
                'version_label' => $attachment->versionLabel,
            ];
        }

        return [
            'slug' => $data->slug,
            'source_kind' => $data->sourceKind->value,
            'source_request_id' => $data->sourceRequestId,
            'editorial_origin' => $data->editorialOrigin,
            'version' => [
                'version_label' => $data->version->versionLabel,
                'body' => $data->version->body,
                'limits' => $data->version->limits,
                'technologies' => $technologies,
            ],
        ];
    }
}
