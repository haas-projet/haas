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

    public function view(?User $actor, HelpRequest $request): bool
    {
        return $request->hidden_at === null && $this->access->verified($request->author)
            && ($request->state !== HelpRequestState::Draft || $this->access->owns($actor, $request->author_id));
    }
}
