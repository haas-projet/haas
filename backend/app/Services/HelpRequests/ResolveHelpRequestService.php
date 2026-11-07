<?php

namespace App\Services\HelpRequests;

use App\Data\HelpRequests\ResolutionData;
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
use App\Models\Resolution;
use App\Models\User;
use App\Queries\Collaboration\VisibleProposalsQuery;
use App\Queries\HelpRequests\FindVisibleHelpRequestQuery;
use App\Queries\HelpRequests\FindVisibleResolutionQuery;
use App\Services\Audit\AuditWriter;
use App\Services\Idempotency\IdempotencyService;
use App\Services\Notifications\NotificationOutbox;
use App\Support\HelpRequests\ResolutionContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ResolveHelpRequestService
{
    public function __construct(private readonly IdempotencyService $idempotency, private readonly AuditWriter $audit,
        private readonly NotificationOutbox $notifications, private readonly FindVisibleHelpRequestQuery $parents,
        private readonly VisibleProposalsQuery $proposals, private readonly FindVisibleResolutionQuery $resolutions) {}

    public function resolve(User $actor, string $parentId, ResolutionData $data, IdempotencyKey $key): Resolution
    {
        $parent = $this->parents->get($actor, strtolower($parentId));
        Gate::forUser($actor)->authorize('resolve', $parent);
        $proposal = $this->proposals->forActor($actor)->where('request_id', $parent->id)->findOrFail($data->proposalId);

        return DB::transaction(function () use ($actor, $parent, $proposal, $data, $key): Resolution {
            // Accounts first, globally sorted; never acquire another account after the parent.
            User::whereIn('id', [$actor->id, $parent->author_id, $proposal->author_id])->orderBy('id')->lockForUpdate()->get();

            return $this->idempotency->execute($actor, new IdempotencyData('POST /api/v1/requests/'.$parent->id.'/resolve', $key, $data->payload()),
                function (User $current) use ($parent, $proposal): void {
                    $this->authorizeLocked($current, $parent, $proposal);
                },
                function (User $current) use ($parent, $proposal, $data): StoredCommandResult {
                    [$lockedParent, $lockedProposal] = $this->authorizeLocked($current, $parent, $proposal);
                    if (! in_array($lockedParent->state, [HelpRequestState::Open, HelpRequestState::InProgress], true)
                        || $lockedProposal->state !== ProposalState::Proposed
                        || $lockedParent->lock_version !== $data->lockVersion || $lockedProposal->lock_version !== $data->proposalLockVersion
                        || Resolution::where('request_id', $lockedParent->id)->whereNull('revoked_at')->exists()) {
                        throw new ResolutionConflict;
                    }
                    ResolutionContent::validate($data->payload());
                    $resolution = new Resolution;
                    $resolution->forceFill(['request_id' => $lockedParent->id, 'proposal_id' => $lockedProposal->id,
                        'accepted_by' => $current->id, 'proposal_version' => $lockedProposal->lock_version,
                        'environment' => $data->environment, 'validation_note' => $data->validationNote, 'accepted_at' => now()->utc(), 'revoked_at' => null])->save();
                    // The proposal's content and its revision remain exactly the selected version.
                    $lockedProposal->state = ProposalState::Accepted;
                    $lockedProposal->save();
                    $lockedParent->state = HelpRequestState::Resolved;
                    $lockedParent->lock_version++;
                    $lockedParent->save();
                    $this->audit->helpRequestResolved($current, $lockedParent, $lockedProposal, $resolution);
                    if ($lockedProposal->author_id !== $current->id) {
                        $this->notifications->record(new NotificationEvent($resolution->id, $lockedProposal->author_id, 'resolution.accepted'));
                    }

                    return new StoredCommandResult(200, ['resolution_id' => $resolution->id], $lockedParent->lock_version);
                },
                function (User $current, StoredCommandResult $result) use ($parent, $proposal): Resolution {
                    if ($result->status !== 200 || array_keys($result->references) !== ['resolution_id']) {
                        throw new IdempotencyStorageFailed('Résultat de résolution mémorisé invalide.');
                    }
                    $resolution = $this->resolutions->get($current, $parent->id);
                    if ($resolution->id !== $result->references['resolution_id']) {
                        // A legitimate later replacement is a stale result, not corrupt storage.
                        throw new ResolutionConflict;
                    }
                    if ($resolution->accepted_by !== $current->id || $resolution->proposal_id !== $proposal->id) {
                        throw new IdempotencyStorageFailed('Référence de résolution mémorisée invalide.');
                    }
                    $resolvedParent = $resolution->request;
                    if (! $resolvedParent instanceof HelpRequest) {
                        throw new IdempotencyStorageFailed('Parent de résolution mémorisé introuvable.');
                    }
                    if ($resolvedParent->lock_version !== $result->version) {
                        throw new ResolutionConflict;
                    }

                    return $resolution;
                });
        });
    }

    /** @return array{HelpRequest, Proposal} */
    private function authorizeLocked(User $actor, HelpRequest $originalParent, Proposal $originalProposal): array
    {
        $parent = HelpRequest::whereKey($originalParent->id)->lockForUpdate()->firstOrFail();
        $this->parents->get($actor, $parent->id);
        Gate::forUser($actor)->authorize('resolve', $parent);
        $proposal = Proposal::whereKey($originalProposal->id)->where('request_id', $parent->id)->lockForUpdate()->firstOrFail();
        $this->proposals->get($actor, $proposal->id);
        if ($parent->author_id !== $originalParent->author_id || $proposal->author_id !== $originalProposal->author_id) {
            throw new ResolutionConflict;
        }

        return [$parent, $proposal];
    }
}
