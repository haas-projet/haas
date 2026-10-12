<?php

namespace App\Policies;

use App\Enums\HelpRequests\HelpRequestState;
use App\Models\HelpRequest;
use App\Models\User;
use App\Support\Identity\MemberAccess;

final class HelpRequestPolicy
{
    public function __construct(private readonly MemberAccess $access) {}

    public function create(User $actor): bool
    {
        return $this->access->verified($actor);
    }

    public function update(User $actor, HelpRequest $request): bool
    {
        return $this->access->owns($actor, $request->author_id) && $request->hidden_at === null && $request->state !== HelpRequestState::Archived;
    }

    public function view(?User $actor, HelpRequest $request): bool
    {
        return $request->hidden_at === null && $this->access->verified($request->author)
            && ($request->state !== HelpRequestState::Draft || $this->access->owns($actor, $request->author_id));
    }

    public function resolve(User $actor, HelpRequest $request): bool
    {
        // Resolved is allowed here only so the same intention can be replayed.
        // The command checks whether it is a new transition or an exact replay.
        return $this->access->owns($actor, $request->author_id) && $request->hidden_at === null
            && in_array($request->state, [HelpRequestState::Open, HelpRequestState::InProgress, HelpRequestState::Resolved], true);
    }

    public function reopen(User $actor, HelpRequest $request): bool
    {
        // Open/InProgress permit the authorization of a replay; only the Service
        // may apply the Resolved→Open transition or reject a changed result.
        return $this->access->owns($actor, $request->author_id) && $request->hidden_at === null
            && in_array($request->state, [HelpRequestState::Resolved, HelpRequestState::Open, HelpRequestState::InProgress], true);
    }
}
