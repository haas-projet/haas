<?php

declare(strict_types=1);

namespace App\Queries\Comparisons;

use App\Data\Lab\LabDefinitionData;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Identity\AccountStatus;
use App\Infrastructure\Comparisons\ComparisonProfileRegistry;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Comparisons\ComparisonProfile;
use App\Models\Lab\LabDefinition;
use App\Queries\HelpRequests\VisibleHelpRequestsQuery;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * BV206 — récupère le profil B1 approuvé et sa définition B33 associée pour
 * un lancement. Le profil doit être enabled, sa candidate publiée + visible,
 * la définition B33 doit matcher le registre immuable, et le switch
 * `config('lab.enabled')` doit être true (sinon 503, voir
 * StartComparisonService).
 */
final class ApprovedProfileQuery
{
    public function __construct(
        private readonly ComparisonProfileRegistry $registry,
        private readonly VisibleHelpRequestsQuery $requests,
    ) {}

    /** @return array{profile: ComparisonProfile, version: CapsuleVersion, definition: LabDefinitionData} */
    public function get(string $profileId): array
    {
        $profile = ComparisonProfile::query()
            ->whereKey($profileId)
            ->where('family_key', $this->registry->familyKey())
            ->where('enabled', true)
            ->first();
        if ($profile === null) {
            throw new NotFoundHttpException;
        }
        $version = CapsuleVersion::on('pgsql')
            ->whereKey($profile->candidate_version_id)
            ->where('state', CapsuleVersionState::Published)
            ->whereHas('capsule', function (Builder $capsule): void {
                $capsule->where('visibility', CapsuleVisibility::Visible)
                    ->whereHas('owner', fn (Builder $owner) => $owner->where('status', AccountStatus::Active)->whereNotNull('email_verified_at'))
                    ->where(fn (Builder $source) => $source->whereNull('source_request_id')
                        ->orWhereIn('source_request_id', $this->requests->forActor(null)->select('help_requests.id')));
            })
            ->first();
        if ($version === null) {
            throw new NotFoundHttpException;
        }
        $definition = LabDefinition::query()
            ->where('version_id', $version->id)
            ->where('enabled', true)
            ->first();
        if ($definition === null || ! $this->registry->labIntegrity()) {
            throw new NotFoundHttpException;
        }
        $data = new LabDefinitionData(
            id: $definition->id,
            versionId: $version->id,
            release: $definition->release,
            moduleVersion: $definition->module_version,
            codeDigest: $definition->code_digest,
            suiteDigest: $definition->suite_digest,
            versionDigest: $definition->version_digest,
        );

        return ['profile' => $profile, 'version' => $version, 'definition' => $data];
    }
}
