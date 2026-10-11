<?php

namespace App\Services\Collaboration;

use App\Data\Collaboration\ProposalData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\Notifications\NotificationEvent;
use App\Enums\Collaboration\ProposalState;
use App\Enums\HelpRequests\HelpRequestState;
use App\Exceptions\Collaboration\ProposalHistoryRejected;
use App\Exceptions\Collaboration\ProposalVersionConflict;
use App\Exceptions\HelpRequests\HelpRequestVersionConflict;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\ProposalRevision;
use App\Models\User;
use App\Queries\Collaboration\VisibleProposalsQuery;
use App\Queries\HelpRequests\FindVisibleHelpRequestQuery;
use App\Rules\NoLikelySecret;
use App\Rules\ProposalText;
use App\Services\Audit\AuditWriter;
use App\Services\Idempotency\IdempotencyService;
use App\Services\Notifications\NotificationOutbox;
use App\Support\Collaboration\ProposalContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class ProposalService
{
    public function __construct(private readonly IdempotencyService $idempotency, private readonly AuditWriter $audit,
        private readonly NotificationOutbox $notifications, private readonly VisibleProposalsQuery $visible, private readonly FindVisibleHelpRequestQuery $parents) {}

    public function create(User $actor, string $parentId, ProposalData $data, IdempotencyKey $key): Proposal
    {
        if ($data->lockVersion !== null) {
            throw new InvalidArgumentException('La création ne reçoit pas de version.');
        }

        return $this->execute($actor, strtolower($parentId), null, $data, $key);
    }

    public function update(User $actor, string $id, ProposalData $data, IdempotencyKey $key): Proposal
    {
        if ($data->lockVersion === null) {
            throw new InvalidArgumentException('Version requise pour modifier une proposition.');
        }
        $proposal = $this->visible->get($actor, strtolower($id));

        return $this->execute($actor, $proposal->request_id, $proposal->id, $data, $key);
    }

    private function execute(User $actor, string $parentId, ?string $id, ProposalData $data, IdempotencyKey $key): Proposal
    {
        $parent = $this->parents->get($actor, $parentId);

        // Même ordre global que les commandes de demande/commentaire : comptes, parent, enfant.
        return DB::transaction(function () use ($actor, $parent, $id, $data, $key): Proposal {
            User::whereIn('id', [$actor->id, $parent->author_id])->orderBy('id')->lockForUpdate()->get();
            $route = $id === null ? 'POST /api/v1/requests/'.$parent->id.'/proposals' : 'PATCH /api/v1/proposals/'.$id;

            return $this->idempotency->execute($actor, new IdempotencyData($route, $key, $data->payload()),
                function (User $current) use ($parent, $id): void {
                    $this->authorizeLocked($current, $parent->id, $id);
                },
                function (User $current) use ($parent, $id, $data): StoredCommandResult {
                    $lockedParent = $this->authorizeLocked($current, $parent->id, $id);
                    if ($id === null) {
                        ProposalContent::validate($data->changes);
                        $proposal = new Proposal;
                        $proposal->forceFill([...$data->changes, 'request_id' => $lockedParent->id, 'author_id' => $current->id, 'state' => ProposalState::Proposed])->save();
                        if ($lockedParent->state === HelpRequestState::Open) {
                            if ($lockedParent->lock_version >= 2147483647) {
                                throw new HelpRequestVersionConflict;
                            }
                            $lockedParent->state = HelpRequestState::InProgress;
                            $lockedParent->lock_version++;
                            $lockedParent->save();
                            $this->audit->helpRequestStarted($current, $lockedParent, $proposal);
                        }
                    } else {
                        $proposal = Proposal::whereKey($id)->lockForUpdate()->firstOrFail();
                        if ($proposal->lock_version !== $data->lockVersion) {
                            throw new ProposalVersionConflict;
                        }
                        $content = array_replace($proposal->only(ProposalContent::FIELDS), $data->changes);
                        foreach (array_slice(ProposalContent::FIELDS, 0, 4) as $field) {
                            if (is_string($content[$field])) {
                                $content[$field] = ProposalText::trim($content[$field]);
                            }
                        }
                        ProposalContent::validate($content);
                        if (! ProposalRevision::where('proposal_id', $id)->exists()) {
                            // Pas de copie d'un secret connu dans l'historique public, ni d'expurgation fictive.
                            if (Validator::make($proposal->only(ProposalContent::FIELDS), array_fill_keys(ProposalContent::FIELDS, [new NoLikelySecret]))->fails()) {
                                throw new ProposalHistoryRejected;
                            }
                            $this->revision($proposal, 'before_edit');
                        }
                        $proposal->fill($content);
                        $proposal->lock_version++;
                        $proposal->edited_at = now()->utc()->toImmutable();
                        $proposal->save();
                    }
                    $proposal->setRelation('request', $lockedParent);
                    $this->revision($proposal, $id === null ? 'created' : 'updated');
                    $this->audit->proposalChanged($current, $proposal, $id === null);
                    if ($id === null && $current->id !== $lockedParent->author_id) {
                        $this->notifications->record(new NotificationEvent($proposal->id, $lockedParent->author_id, 'proposal.created'));
                    }

                    return new StoredCommandResult($id === null ? 201 : 200, ['proposal_id' => $proposal->id], $proposal->lock_version);
                },
                function (User $current, StoredCommandResult $result) use ($parent, $id): Proposal {
                    if ($result->status !== ($id === null ? 201 : 200) || array_keys($result->references) !== ['proposal_id'] || ($id !== null && $result->references['proposal_id'] !== $id)) {
                        throw new IdempotencyStorageFailed('Résultat de proposition mémorisé invalide.');
                    }
                    $proposal = $this->visible->get($current, $result->references['proposal_id']);
                    if ($proposal->author_id !== $current->id || $proposal->request_id !== $parent->id) {
                        throw new IdempotencyStorageFailed('Référence de proposition mémorisée invalide.');
                    }
                    Gate::forUser($current)->authorize('update', $proposal);
                    if ($proposal->lock_version !== $result->version) {
                        throw new ProposalVersionConflict;
                    }

                    return $proposal;
                });
        });
    }

    private function authorizeLocked(User $actor, string $parentId, ?string $id): HelpRequest
    {
        $parent = HelpRequest::whereKey($parentId)->lockForUpdate()->firstOrFail();
        $this->parents->get($actor, $parentId);
        Gate::forUser($actor)->authorize('create', [Proposal::class, $parent]);
        if ($id !== null) {
            $proposal = Proposal::whereKey($id)->where('request_id', $parentId)->lockForUpdate()->firstOrFail();
            $this->visible->get($actor, $id);
            $proposal->setRelation('request', $parent);
            Gate::forUser($actor)->authorize('update', $proposal);
        }

        return $parent;
    }

    private function revision(Proposal $proposal, string $action): void
    {
        $revision = new ProposalRevision;
        $revision->forceFill([...$proposal->only(ProposalContent::FIELDS), 'proposal_id' => $proposal->id,
            'proposal_version' => $proposal->lock_version, 'action' => $action, 'occurred_at' => now()->utc()])->save();
    }
}
