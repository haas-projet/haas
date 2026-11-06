<?php

declare(strict_types=1);

namespace App\Http\Resources\Capsules;

use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Technology;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Carte catalogue (B26, §14).
 *
 * N'expose : slug, kind de provenance, origine éditoriale (publique),
 * version publiée courante (label, published_at, technologies) et date
 * de dernière publication. Ne révèle NI `source_request_id` (RM06),
 * NI reviewer, NI note, NI identifiant d'artefact privé.
 *
 * @property-read Capsule $resource
 */
final class CapsuleCataloguePreview extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Capsule $capsule */
        $capsule = $this->resource;
        /** @var CapsuleVersion|null $latest */
        $latest = $capsule->getRelation('latestPublished');
        $technologies = $latest === null ? [] : $latest->technologies->map(static function (Technology $tech): array {
            /** @var Pivot $pivot */
            $pivot = $tech->getRelation('pivot');

            return [
                'id' => $tech->id,
                'slug' => $tech->slug,
                'version_label' => $pivot->getAttribute('version_label'),
            ];
        })->all();

        return [
            'slug' => $capsule->slug,
            'source' => [
                'kind' => $capsule->source_request_id === null ? 'editorial' : 'help_request',
                'editorial_origin' => $capsule->editorial_origin,
            ],
            'version' => $latest === null ? null : [
                'version_label' => $latest->version_label,
                'published_at' => $latest->published_at?->toIso8601String(),
                'technologies' => $technologies,
            ],
            'last_published_at' => $latest?->published_at?->toIso8601String(),
        ];
    }
}
