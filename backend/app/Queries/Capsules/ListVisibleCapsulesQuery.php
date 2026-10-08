<?php

declare(strict_types=1);

namespace App\Queries\Capsules;

use App\Data\Capsules\CapsuleCatalogueFilterData;
use App\Enums\Capsules\CapsuleVersionState;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
        if ($filter->sort === 'relevance') {
            $slugPattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $filter->search).'%';
            $query->orderByRaw("CASE WHEN slug ILIKE ? ESCAPE '!' THEN 0 WHEN editorial_origin ILIKE ? ESCAPE '!' THEN 1 ELSE 2 END", [$slugPattern, $slugPattern]);
        }
        $query->leftJoinSub(
            DB::table('capsule_versions')->select('capsule_id')
                ->selectRaw('max(published_at) as last_published_at')
                ->where('state', CapsuleVersionState::Published->value)
                ->groupBy('capsule_id'),
            'last_pub',
            'last_pub.capsule_id',
            '=',
            'capsules.id',
        );
        $query->orderByDesc('last_pub.last_published_at')->orderBy('capsules.id');
        /** @var LengthAwarePaginator<int, Capsule> $page */
        $page = $query->select('capsules.*')->paginate($filter->page->perPage, ['*'], 'page', $filter->page->page);
        $this->hydrateLatestPublished(array_values($page->getCollection()->all()));

        return $page;
    }

    /** @param list<Capsule> $capsules */
    private function hydrateLatestPublished(array $capsules): void
    {
        if ($capsules === []) {
            return;
        }
        $ids = array_map(static fn (Capsule $c): string => $c->id, $capsules);
        $latest = CapsuleVersion::query()
            ->whereIn('capsule_id', $ids)
            ->where('state', CapsuleVersionState::Published)
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->with('technologies')
            ->get();
        $byCapsule = [];
        foreach ($latest as $version) {
            if (! isset($byCapsule[$version->capsule_id])) {
                $byCapsule[$version->capsule_id] = $version;
            }
        }
        foreach ($capsules as $capsule) {
            $capsule->setRelation('latestPublished', $byCapsule[$capsule->id] ?? null);
        }
    }
}
