<?php

namespace App\Services\HelpRequests;

use App\Data\HelpRequests\ReopeningData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\Notifications\NotificationEvent;
use App\Enums\Collaboration\ProposalState;
use App\Enums\HelpRequests\HelpRequestState;
use App\Exceptions\HelpRequests\ResolutionConflict;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\RequestReopening;
use App\Models\Resolution;
use App\Models\User;
use App\Queries\HelpRequests\FindVisibleHelpRequestQuery;
use App\Services\Idempotency\IdempotencyService;
use App\Services\Notifications\NotificationOutbox;
use App\Support\HelpRequests\ReopeningContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ReopenHelpRequestService
{
    public function __construct(private readonly IdempotencyService $idempotency, private readonly FindVisibleHelpRequestQuery $parents,
        private readonly WriteRequestReopeningAudit $audit, private readonly NotificationOutbox $notifications) {}

    public function reopen(User $actor, string $parentId, ReopeningData $data, IdempotencyKey $key): RequestReopening
    {
        $parent = $this->parents->get($actor, strtolower($parentId));
        Gate::forUser($actor)->authorize('reopen', $parent);
        // This private command does not depend on the former contributor's
        // current public visibility. The parent owner can revoke their decision.
        $candidate = Resolution::where('request_id', $parent->id)->whereNull('revoked_at')->first();
        if ($candidate === null) {
            $last = RequestReopening::where('request_id', $parent->id)->orderByDesc('request_version_after')->first();
            $candidate = $last?->resolution;
        }
        $proposal = $candidate === null ? null : Proposal::whereKey($candidate->proposal_id)->where('request_id', $parent->id)->first();
        $accountIds = [$actor->id, $parent->author_id];
        if ($proposal !== null) {
            $accountIds[] = $proposal->author_id;
        }

        return DB::transaction(function () use ($actor, $parent, $candidate, $proposal, $accountIds, $data, $key): RequestReopening {
            User::whereIn('id', $accountIds)->orderBy('id')->lockForUpdate()->get();

            return $this->idempotency->execute($actor, new IdempotencyData('POST /api/v1/requests/'.$parent->id.'/reopen', $key, $data->payload()),
                function (User $current) use ($parent, $candidate, $proposal): void {
                    $this->authorizeLocked($current, $parent, $candidate, $proposal);
                },
                function (User $current) use ($parent, $candidate, $proposal, $data): StoredCommandResult {
                    [$lockedParent, $lockedResolution, $lockedProposal] = $this->authorizeLocked($current, $parent, $candidate, $proposal);
                    if ($lockedParent->state !== HelpRequestState::Resolved || $lockedParent->lock_version !== $data->lockVersion
                        || $lockedResolution === null || $lockedProposal === null || $lockedResolution->revoked_at !== null
                        || $lockedResolution->accepted_by !== $current->id || $lockedProposal->state !== ProposalState::Accepted
                        || ($lockedResolution->proposal_version !== null && $lockedResolution->proposal_version !== $lockedProposal->lock_version)
                        || RequestReopening::where('resolution_id', $lockedResolution->id)->exists()) {
                        throw new ResolutionConflict;
                    }
                    ReopeningContent::validate($data->payload());
                    $date = now()->utc()->toImmutable();
                    $lockedResolution->revoked_at = $date;
                    $lockedResolution->save();
                    $lockedParent->state = HelpRequestState::Open;
                    $lockedParent->lock_version++;
                    $lockedParent->save();
                    $reopening = (new RequestReopening)->forceFill(['request_id' => $lockedParent->id, 'resolution_id' => $lockedResolution->id,
                        'reopened_by' => $current->id, 'reason' => $data->reason, 'reopened_at' => $date,
                        'request_version_before' => $data->lockVersion, 'request_version_after' => $lockedParent->lock_version]);
                    $reopening->save();
                    $this->audit->write($current, $lockedParent, $lockedResolution, $reopening);
                    if ($lockedProposal->author_id !== $current->id) {
                        $this->notifications->record(new NotificationEvent($reopening->id, $lockedProposal->author_id, 'request.reopened'));
                    }

                    return new StoredCommandResult(200, ['reopening_id' => $reopening->id], $lockedParent->lock_version);
                },
                function (User $current, StoredCommandResult $result) use ($parent): RequestReopening {
                    if ($result->status !== 200 || array_keys($result->references) !== ['reopening_id']) {
                        throw new IdempotencyStorageFailed('Résultat de réouverture mémorisé invalide.');
                    }
                    $reopening = RequestReopening::whereKey($result->references['reopening_id'])->where('request_id', $parent->id)
                        ->with(['request', 'resolution', 'author:id,handle'])->firstOrFail();
                    $currentParent = $reopening->request;
                    $resolution = $reopening->resolution;
                    if (! $currentParent instanceof HelpRequest || ! $resolution instanceof Resolution
                        || $reopening->reopened_by !== $current->id || $resolution->request_id !== $currentParent->id) {
                        throw new IdempotencyStorageFailed('Référence de réouverture mémorisée invalide.');
                    }
                    $this->parents->get($current, $currentParent->id);
                    Gate::forUser($current)->authorize('reopen', $currentParent);
                    if ($currentParent->state !== HelpRequestState::Open || $currentParent->lock_version !== $result->version
                        || $reopening->request_version_after !== $result->version || $resolution->revoked_at === null
                        || Resolution::where('request_id', $currentParent->id)->whereNull('revoked_at')->exists()) {
                        throw new ResolutionConflict;
                    }

                    return $reopening;
                });
        });
    }

    /** @return array{HelpRequest, Resolution|null, Proposal|null} */
    private function authorizeLocked(User $actor, HelpRequest $original, ?Resolution $resolution, ?Proposal $proposal): array
    {
        $parent = HelpRequest::whereKey($original->id)->lockForUpdate()->firstOrFail();
        $this->parents->get($actor, $parent->id);
        Gate::forUser($actor)->authorize('reopen', $parent);
        if ($resolution === null || $proposal === null) {
            return [$parent, null, null];
        }
        $lockedProposal = Proposal::whereKey($proposal->id)->where('request_id', $parent->id)->lockForUpdate()->firstOrFail();
        $lockedResolution = Resolution::whereKey($resolution->id)->where('request_id', $parent->id)->lockForUpdate()->firstOrFail();
        if ($lockedResolution->proposal_id !== $lockedProposal->id || $lockedProposal->author_id !== $proposal->author_id
            || $parent->author_id !== $original->author_id) {
            throw new ResolutionConflict;
        }

        return [$parent, $lockedResolution, $lockedProposal];
    }
}
