<?php

declare(strict_types=1);

namespace App\Http\Resources\Capsules;

use App\Models\Capsules\CapsuleVersion;
use App\Models\Technology;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de publication. N'expose JAMAIS note de revue, reviewer_id,
 * chemin privé d'artefact ni données personnelles. Les champs retenus
 * sont strictement ceux documentés par le contrat de B25.
 *
 * @property-read CapsuleVersion $resource
 */
final class CapsuleVersionPublishedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CapsuleVersion $version */
        $version = $this->resource;
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
            'id' => $version->id,
            'capsule_id' => $version->capsule_id,
            'version_label' => $version->version_label,
            'state' => $version->state->value,
            'body' => $version->body,
            'limits' => $version->limits,
            'content_digest' => $version->content_digest,
            'lock_version' => (int) $version->lock_version,
            'published_at' => $version->published_at?->toIso8601String(),
            'technologies' => $technologies,
        ];
    }
}
