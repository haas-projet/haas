<?php

namespace App\Policies;

use App\Enums\Collaboration\ProposalState;
use App\Enums\HelpRequests\HelpRequestState;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\User;
use App\Support\Identity\MemberAccess;

final class ProposalPolicy
{
    public function __construct(private readonly MemberAccess $access, private readonly HelpRequestPolicy $requests) {}

    public function create(User $actor, HelpRequest $parent): bool
    {
        return $this->access->verified($actor) && $this->requests->view($actor, $parent)
            && in_array($parent->state, [HelpRequestState::Open, HelpRequestState::InProgress], true);
    }

    public function update(User $actor, Proposal $proposal): bool
    {
        return $this->access->owns($actor, $proposal->author_id) && $proposal->state === ProposalState::Proposed
            && $proposal->hidden_at === null && $proposal->request !== null && $this->create($actor, $proposal->request);
    }
}
