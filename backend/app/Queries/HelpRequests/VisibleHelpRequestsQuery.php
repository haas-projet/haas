<?php

namespace App\Queries\HelpRequests;

use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\AccountStatus;
use App\Models\HelpRequest;
use App\Models\User;
use App\Support\Identity\MemberAccess;
use Illuminate\Database\Eloquent\Builder;

final class VisibleHelpRequestsQuery
{
    public function __construct(private readonly MemberAccess $access) {}

    /** @return Builder<HelpRequest> */
    public function forActor(?User $actor): Builder
    {
        return HelpRequest::query()->whereNull('hidden_at')
            ->whereHas('author', fn (Builder $author) => $author->where('status', AccountStatus::Active)->whereNotNull('email_verified_at'))
            ->where(function (Builder $visible) use ($actor): void {
                $visible->where('state', '!=', HelpRequestState::Draft);
                if ($this->access->verified($actor)) {
                    $visible->orWhere('author_id', $actor?->id);
                }
            })
            ->with(['author:id,handle,status,email_verified_at', 'technologies' => fn ($technologies) => $technologies->orderBy('slug')->orderBy('technologies.id')]);
    }
}
