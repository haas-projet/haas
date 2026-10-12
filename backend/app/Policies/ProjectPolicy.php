<?php

namespace App\Policies;

use App\Enums\Projects\ProjectPublicationState;
use App\Models\Project;
use App\Models\User;
use App\Support\Identity\MemberAccess;

final class ProjectPolicy
{
    public function __construct(private readonly MemberAccess $access) {}

    public function create(User $actor): bool
    {
        return $this->access->verified($actor);
    }

    public function view(?User $actor, Project $project): bool
    {
        return $project->hidden_at === null && $this->access->verified($project->owner)
            && ($this->access->owns($actor, $project->owner_id)
                || ($project->publication_state !== ProjectPublicationState::Draft && $project->published_at !== null));
    }

    public function update(User $actor, Project $project): bool
    {
        return $this->access->owns($actor, $project->owner_id) && $project->hidden_at === null
            && $project->publication_state !== ProjectPublicationState::Archived;
    }
}
