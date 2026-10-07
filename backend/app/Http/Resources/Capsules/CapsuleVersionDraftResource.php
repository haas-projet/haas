<?php

declare(strict_types=1);

namespace App\Http\Resources\Capsules;

use App\Models\Capsules\CapsuleVersion;
use App\Models\Technology;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read CapsuleVersion $resource */
final class CapsuleVersionDraftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CapsuleVersion $version */
        $version = $this->resource;
        $technologies = $version->technologies()->get(['technologies.id', 'technologies.slug'])->map(static function (Technology $t): array {
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
            'lock_version' => (int) $version->lock_version,
            'technologies' => $technologies,
        ];
    }
}
