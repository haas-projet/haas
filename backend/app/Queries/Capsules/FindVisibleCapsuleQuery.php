<?php

declare(strict_types=1);

namespace App\Queries\Capsules;

use App\Enums\Capsules\CapsuleVersionState;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Détail du catalogue public (B26).
 *
 * Résout une capsule par `slug` (visible, au moins une version publiée),
 * puis la version demandée (par défaut la plus récente publiée). Les
 * versions non publiées ou retirées renvoient 404 : aucune lecture
 * détournée via « lien direct » (RM06, AC25).
 */
final class FindVisibleCapsuleQuery
{
    public function __construct(private readonly VisibleCapsulesQuery $visible) {}

    /** @return array{capsule: Capsule, version: CapsuleVersion, history: list<CapsuleVersion>} */
    public function get(string $slug, ?string $versionLabel = null): array
    {
        $capsule = $this->visible->builder()
            ->whereRaw('lower(slug) = ?', [strtolower($slug)])
            ->with(['owner'])
            ->first();
        if ($capsule === null) {
            throw new NotFoundHttpException;
        }

        $publishedQuery = CapsuleVersion::query()
            ->where('capsule_id', $capsule->id)
            ->where('state', CapsuleVersionState::Published);

        $version = $versionLabel === null
            ? (clone $publishedQuery)->orderByDesc('published_at')->orderBy('id')->first()
            : (clone $publishedQuery)->where('version_label', $versionLabel)->first();
        if ($version === null) {
            throw new NotFoundHttpException;
        }
        $version->load(['technologies', 'contributors.user']);

        /** @var list<CapsuleVersion> $history */
        $history = (clone $publishedQuery)
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->get()
            ->all();

        return ['capsule' => $capsule, 'version' => $version, 'history' => $history];
    }
}
