<?php

declare(strict_types=1);

namespace App\Queries\Lab;

use App\Data\Lab\LabCapabilityData;
use App\Data\Lab\LabDefinitionData;
use App\Data\Lab\LabSelectionData;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Identity\AccountStatus;
use App\Infrastructure\Lab\ApprovedLabRegistry;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Lab\LabDefinition;
use App\Queries\HelpRequests\VisibleHelpRequestsQuery;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class LabDefinitionQuery
{
    public function __construct(private readonly ApprovedLabRegistry $registry, private readonly VisibleHelpRequestsQuery $requests) {}

    public function get(string $versionId): LabCapabilityData
    {
        $version = CapsuleVersion::on('pgsql')->whereKey($versionId)->where('state', CapsuleVersionState::Published)
            ->whereHas('capsule', function (Builder $capsule): void {
                $capsule->where('visibility', CapsuleVisibility::Visible)
                    ->whereHas('owner', fn (Builder $owner) => $owner->where('status', AccountStatus::Active)->whereNotNull('email_verified_at'))
                    ->where(fn (Builder $source) => $source->whereNull('source_request_id')
                        ->orWhereIn('source_request_id', $this->requests->forActor(null)->select('help_requests.id')));
            })->firstOrFail();
        $definition = LabDefinition::query()->where('version_id', $version->id)->where('enabled', true)->first();
        if (config('lab.enabled') !== true || $definition === null || ! $this->registry->matches($definition)
            || $version->content_digest === null || $definition->version_digest !== $version->content_digest) {
            return new LabCapabilityData($version->id, null);
        }

        return new LabCapabilityData($version->id, new LabDefinitionData($definition->id, $version->id,
            $definition->release, $definition->module_version, $definition->code_digest,
            $definition->suite_digest, $definition->version_digest));
    }

    /** Validates an internal B34 selection; creates neither a run nor an observed result. */
    public function selection(LabSelectionData $selection): LabDefinitionData
    {
        return $this->get($selection->versionId)->definition ?? throw new ServiceUnavailableHttpException;
    }
}
