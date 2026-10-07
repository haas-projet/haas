<?php

namespace App\Policies;

use App\Enums\HelpRequests\HelpRequestState;
use App\Models\Comment;
use App\Models\HelpRequest;
use App\Models\User;
use App\Support\Identity\MemberAccess;

final class CommentPolicy
{
    public function __construct(private readonly MemberAccess $access, private readonly HelpRequestPolicy $requests) {}

    public function create(User $actor, HelpRequest $parent): bool
    {
        return $this->access->verified($actor) && $this->requests->view($actor, $parent) && ! in_array($parent->state, [HelpRequestState::Draft, HelpRequestState::Archived], true);
    }

    public function update(User $actor, Comment $comment): bool
    {
        return $this->access->owns($actor, $comment->author_id) && $comment->hidden_at === null && $comment->request !== null && $this->create($actor, $comment->request);
    }
}
