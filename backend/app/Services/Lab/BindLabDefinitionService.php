<?php

declare(strict_types=1);

namespace App\Services\Lab;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Exceptions\Lab\LabDefinitionBindingRejected;
use App\Infrastructure\Lab\ApprovedLabRegistry;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\HelpRequest;
use App\Models\Lab\LabDefinition;
use App\Models\User;
use App\Queries\HelpRequests\VisibleHelpRequestsQuery;
use App\Support\Identity\MemberAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Internal release operation only: never publishes a capsule or starts a run. */
final class BindLabDefinitionService
{
    public function __construct(
        private readonly ApprovedLabRegistry $registry,
        private readonly VisibleHelpRequestsQuery $requests,
        private readonly MemberAccess $access,
        private readonly WriteLabDefinitionAudit $audit,
    ) {}

    public function bind(string $versionId, string $release): LabDefinition
    {
        Validator::make(['version_id' => $versionId, 'release' => $release], [
            'version_id' => ['required', 'string', 'uuid'],
            'release' => ['required', 'string', Rule::in([$this->registry->manifest()['release']])],
        ])->validate();

        return DB::connection('pgsql')->transaction(function () use ($versionId): LabDefinition {
            $candidate = CapsuleVersion::on('pgsql')->find($versionId);
            $capsule = $candidate === null ? null : Capsule::on('pgsql')->find($candidate->capsule_id);
            if ($capsule === null) {
                throw new LabDefinitionBindingRejected;
            }
            $source = $capsule->source_request_id === null ? null : HelpRequest::on('pgsql')->find($capsule->source_request_id);
            if ($capsule->source_request_id !== null && $source === null) {
                throw new LabDefinitionBindingRejected;
            }
            $accountIds = array_values(array_unique([$capsule->owner_id, ...($source === null ? [] : [$source->author_id])]));
            // Same account -> parent -> child order as community writes; recheck discovered identities below.
            $accounts = User::on('pgsql')->whereIn('id', $accountIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($accountIds as $accountId) {
                if (! $this->access->verified($accounts->get($accountId))) {
                    throw new LabDefinitionBindingRejected;
                }
            }
            if ($source !== null) {
                $currentSource = $this->requests->forActor(null)->whereKey($source->id)->lockForUpdate()->first();
                if ($currentSource === null || $currentSource->author_id !== $source->author_id) {
                    throw new LabDefinitionBindingRejected;
                }
            }
            $currentCapsule = Capsule::on('pgsql')->whereKey($capsule->id)->lockForUpdate()->first();
            if ($currentCapsule === null || $currentCapsule->visibility !== CapsuleVisibility::Visible
                || $currentCapsule->owner_id !== $capsule->owner_id || $currentCapsule->source_request_id !== $capsule->source_request_id) {
                throw new LabDefinitionBindingRejected;
            }
            $version = CapsuleVersion::on('pgsql')->whereKey($versionId)->where('capsule_id', $currentCapsule->id)->lockForUpdate()->first();
            if ($version === null || $version->state !== CapsuleVersionState::Published
                || $version->content_digest === null || ! $this->registry->integrity()) {
                throw new LabDefinitionBindingRejected;
            }
            $existing = LabDefinition::query()->where('version_id', $version->id)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! $this->registry->matches($existing) || $existing->version_digest !== $version->content_digest) {
                    throw new LabDefinitionBindingRejected;
                }

                return $existing; // Retain deliberate disablement and the original audit/snapshot.
            }
            $definition = (new LabDefinition)->forceFill([
                'version_id' => $version->id, ...$this->registry->manifest(),
                'version_digest' => $version->content_digest, 'enabled' => true, 'created_at' => now()->utc(),
            ]);
            $definition->save();
            $this->audit->bound($definition);

            return $definition;
        });
    }
}
