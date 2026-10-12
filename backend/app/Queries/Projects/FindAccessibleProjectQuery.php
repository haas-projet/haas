<?php

namespace App\Queries\Projects;

use App\Enums\Identity\AccountStatus;
use App\Enums\Projects\ProjectPublicationState;
use App\Models\Project;
use App\Models\User;
use App\Support\Identity\MemberAccess;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindAccessibleProjectQuery
{
    public function __construct(private readonly MemberAccess $access) {}

    public function get(?User $actor, string $id): Project
    {
        $project = Project::query()->whereKey(strtolower($id))->whereNull('hidden_at')
            ->whereHas('owner', fn (Builder $owner) => $owner->where('status', AccountStatus::Active)->whereNotNull('email_verified_at'))
            ->where(function (Builder $visible) use ($actor): void {
                $visible->where(fn (Builder $public) => $public->where('publication_state', '!=', ProjectPublicationState::Draft)->whereNotNull('published_at'));
                if ($this->access->verified($actor)) {
                    $visible->orWhere('owner_id', $actor?->id);
                }
            })->with(['owner:id,handle,status,email_verified_at', 'technologies' => fn ($query) => $query->orderBy('slug')->orderBy('technologies.id')])->first();
        if ($project === null) {
            throw new NotFoundHttpException;
        }

        return $project;
    }
}
