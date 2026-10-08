<?php

declare(strict_types=1);

namespace App\Queries\Capsules;

use App\Data\Capsules\CapsuleCatalogueFilterData;
use App\Enums\Capsules\CapsuleVersionState;
use App\Models\Capsules\Capsule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Liste paginée du catalogue public (B26, §14).
 *
 * Préchargée (`with`) de la dernière version publiée et de ses
 * technologies pour prouver l'absence de N+1 sur 30 capsules.
 * Recherche en ILIKE sécurisée (ESCAPE `!`) sur `capsules.slug` et
 * `capsules.editorial_origin`, et sur `capsule_versions.body` /
 * `capsule_versions.limits` de la version la plus récemment publiée.
 * Le corps complet n'est pas renvoyé par la carte ; la correspondance
 * reste côté SQL.
 */
final class ListVisibleCapsulesQuery
{
    public function __construct(private readonly VisibleCapsulesQuery $visible) {}

    /** @return LengthAwarePaginator<int, Capsule> */
    public function get(CapsuleCatalogueFilterData $filter): LengthAwarePaginator
    {
        $query = $this->visible->builder();
        if ($filter->technology !== null) {
            $query->whereHas('versions', fn (Builder $version) => $version->where('state', CapsuleVersionState::Published)
                ->whereHas('technologies', fn (Builder $tech) => $tech->where('technologies.id', $filter->technology)));
        }
        if ($filter->search !== null && $filter->search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filter->search).'%';
            $query->where(function (Builder $search) use ($pattern): void {
                $search->orWhereRaw("slug ILIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("editorial_origin ILIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('versions', fn (Builder $version) => $version->where('state', CapsuleVersionState::Published)
                        ->where(function (Builder $v) use ($pattern): void {
                            $v->whereRaw("body ILIKE ? ESCAPE '!'", [$pattern])
                                ->orWhereRaw("limits ILIKE ? ESCAPE '!'", [$pattern]);
                        }));
            });
        }
        $query->with(['latestPublished' => fn ($relation) => $relation->with('technologies')]);

        if ($filter->sort === 'relevance') {
            $slugPattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $filter->search).'%';
            $query->orderByRaw("CASE WHEN slug ILIKE ? ESCAPE '!' THEN 0 WHEN editorial_origin ILIKE ? ESCAPE '!' THEN 1 ELSE 2 END", [$slugPattern, $slugPattern]);
        }
        $query->leftJoinSub(
            fn ($sub) => $sub->from('capsule_versions')->select('capsule_id')
                ->selectRaw('max(published_at) as last_published_at')
                ->where('state', CapsuleVersionState::Published->value)
                ->groupBy('capsule_id'),
            'last_pub',
            'last_pub.capsule_id',
            '=',
            'capsules.id',
        );
        $query->orderByDesc('last_pub.last_published_at')->orderBy('capsules.id');

        return $query->select('capsules.*')->paginate($filter->page->perPage, ['*'], 'page', $filter->page->page);
    }
}
