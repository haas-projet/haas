<?php

namespace App\Queries\HelpRequests;

use App\Data\HelpRequests\HelpRequestFilterData;
use App\Enums\HelpRequests\HelpRequestState;
use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListHelpRequestsQuery
{
    public function __construct(private readonly VisibleHelpRequestsQuery $visible) {}

    /** @return LengthAwarePaginator<int, HelpRequest> */
    public function get(?User $actor, HelpRequestFilterData $filter): LengthAwarePaginator
    {
        if ($filter->scope === 'mine') {
            if ($actor === null) {
                throw new AuthenticationException;
            }
            Gate::forUser($actor)->authorize('create', HelpRequest::class);
        }
        $query = $this->visible->forActor($actor);
        if ($filter->scope === 'mine') {
            $query->where('author_id', $actor?->id);
        } else {
            $query->where('state', '!=', HelpRequestState::Draft);
        }
        if ($filter->state !== null) {
            $query->where('state', $filter->state);
        }
        if ($filter->technology !== null) {
            $query->whereHas('technologies', fn (Builder $technology) => $technology->where('technologies.id', $filter->technology));
        }
        if ($filter->search !== null && $filter->search !== '') {
            // PostgreSQL ESCAPE explicite : aucun joker fourni par le lecteur.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filter->search).'%';
            $query->where(function (Builder $search) use ($pattern): void {
                foreach (['title', 'goal', 'expected', 'observed', 'attempts', 'environment'] as $field) {
                    $search->orWhereRaw($field." ILIKE ? ESCAPE '!'", [$pattern]);
                }
                $search->orWhereHas('technologies', fn (Builder $technology) => $technology->where(function (Builder $names) use ($pattern): void {
                    $names->whereRaw("name ILIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("slug ILIKE ? ESCAPE '!'", [$pattern]);
                }));
            });
        }
        [$column, $direction] = match ($filter->sort) {
            'oldest' => ['created_at', 'asc'],
            'updated' => ['updated_at', 'desc'],
            default => ['created_at', 'desc'],
        };

        return $query->orderBy($column, $direction)->orderBy('id')->paginate($filter->page->perPage, ['*'], 'page', $filter->page->page);
    }
}
