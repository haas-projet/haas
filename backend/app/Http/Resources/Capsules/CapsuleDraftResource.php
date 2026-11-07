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
 * Resource d'édition : expose au propriétaire la capsule qu'il vient de créer
 * avec la première version-brouillon. Aucune visibilité publique n'est émise
 * d'ici : la Resource publique (B26) traite les versions publiées seulement.
 *
 * @property-read Capsule $resource
 */
final class CapsuleDraftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Capsule $capsule */
        $capsule = $this->resource;
        /** @var CapsuleVersion $version */
        $version = $capsule->getRelation('draftVersion');
        $technologies = $version->technologies->map(static function (Technology $t): array {
            /** @var Pivot $pivot */
            $pivot = $t->getRelation('pivot');

            return [
                'id' => $t->id,
                'slug' => $t->slug,
                'version_label' => $pivot->getAttribute('version_label'),
            ];
        })->all();

        return [
            'id' => $capsule->id,
            'slug' => $capsule->slug,
            'visibility' => $capsule->visibility->value,
            'source' => [
                'kind' => $capsule->source_request_id !== null ? 'help_request' : 'editorial',
                'help_request_id' => $capsule->source_request_id,
                'editorial_origin' => $capsule->editorial_origin,
            ],
            'version' => [
                'id' => $version->id,
                'capsule_id' => $version->capsule_id,
                'version_label' => $version->version_label,
                'state' => $version->state->value,
                'lock_version' => $version->lock_version,
                'body' => $version->body,
                'limits' => $version->limits,
                'technologies' => $technologies,
            ],
        ];
    }
}
