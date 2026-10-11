<?php

declare(strict_types=1);

namespace App\Queries\Capsules;

use App\Data\Capsules\CapsuleCatalogueFilterData;
use App\Enums\Capsules\CapsuleVersionState;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Liste paginée du catalogue public (B26, §14).
 *
 * Une sélection PostgreSQL choisit une dernière version publiée par
 * capsule avant filtre et pagination. Seuls ces IDs sont préchargés
 * avec leurs technologies, sans corps ni historique complet.
 * Recherche en ILIKE sécurisée (ESCAPE `!`) sur `capsules.slug` et
 * `capsules.editorial_origin`, et sur `capsule_versions.body` /
 * `capsule_versions.limits` des versions actuellement publiées.
 * Le corps complet n'est pas renvoyé par la carte ; la correspondance
 * reste côté SQL.
 */
final class ListVisibleCapsulesQuery
{
    public function __construct(private readonly VisibleCapsulesQuery $visible) {}

    /** @return LengthAwarePaginator<int, Capsule> */
    public function get(CapsuleCatalogueFilterData $filter): LengthAwarePaginator
    {
        $latestPublished = DB::table('capsule_versions')
            ->selectRaw('DISTINCT ON (capsule_id) capsule_id, id as latest_published_version_id, published_at as last_published_at')
            ->where('state', CapsuleVersionState::Published->value)
            ->orderBy('capsule_id')->orderByDesc('published_at')->orderBy('id');
        $query = $this->visible->builder()->joinSub($latestPublished, 'last_pub', 'last_pub.capsule_id', '=', 'capsules.id');
        if ($filter->technology !== null) {
            $query->whereExists(fn (QueryBuilder $technology) => $technology->selectRaw('1')
                ->from('capsule_version_technologies')
                ->whereColumn('capsule_version_technologies.version_id', 'last_pub.latest_published_version_id')
                ->where('capsule_version_technologies.technology_id', $filter->technology));
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
        $query->orderByDesc('last_pub.last_published_at')->orderBy('capsules.id');
        /** @var LengthAwarePaginator<int, Capsule> $page */
        $page = $query->select('capsules.*', 'last_pub.latest_published_version_id')->paginate($filter->page->perPage, ['*'], 'page', $filter->page->page);
        $this->hydrateLatestPublished(array_values($page->getCollection()->all()));

        return $page;
    }

    /** @param list<Capsule> $capsules */
    private function hydrateLatestPublished(array $capsules): void
    {
        if ($capsules === []) {
            return;
        }
        $ids = [];
        foreach ($capsules as $capsule) {
            $id = $capsule->getAttribute('latest_published_version_id');
            if (is_string($id)) {
                $ids[] = $id;
            }
        }
        $latest = CapsuleVersion::query()
            ->select(['id', 'capsule_id', 'version_label', 'published_at'])
            ->whereIn('id', $ids)
            ->where('state', CapsuleVersionState::Published)
            ->with('technologies')
            ->get();
        $byCapsule = [];
        foreach ($latest as $version) {
            $byCapsule[$version->capsule_id] = $version;
        }
        foreach ($capsules as $capsule) {
            $capsule->setRelation('latestPublished', $byCapsule[$capsule->id] ?? null);
        }
    }
}
