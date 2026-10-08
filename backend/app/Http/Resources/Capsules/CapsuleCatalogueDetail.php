<?php

declare(strict_types=1);

namespace App\Http\Resources\Capsules;

use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleContributor;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail catalogue (B26). Expose une version publiée précise, sa
 * documentation, ses technologies et ses contributeurs (handle public
 * et rôle), ainsi que l'historique des versions publiées. Jamais
 * `reviewer_id`, note de revue, courriel, `source_request_id`,
 * chemin privé d'artefact ni empreinte de notice.
 */
final class CapsuleCatalogueDetail extends JsonResource
{
    /**
     * @param  array{capsule: Capsule, version: CapsuleVersion, history: list<CapsuleVersion>}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{capsule: Capsule, version: CapsuleVersion, history: list<CapsuleVersion>} $data */
        $data = $this->resource;
        $capsule = $data['capsule'];
        $version = $data['version'];
        $history = $data['history'];

        $technologies = $version->technologies->map(static function (Technology $tech): array {
            /** @var Pivot $pivot */
            $pivot = $tech->getRelation('pivot');

            return [
                'id' => $tech->id,
                'slug' => $tech->slug,
                'version_label' => $pivot->getAttribute('version_label'),
            ];
        })->all();

        $contributors = $version->contributors->map(static function (CapsuleContributor $contributor): array {
            /** @var User|null $user */
            $user = $contributor->getRelation('user');

            return [
                'handle' => $user?->name,
                'role' => $contributor->contribution_role->value,
            ];
        })->all();

        return [
            'slug' => $capsule->slug,
            'source' => [
                'kind' => $capsule->source_request_id === null ? 'editorial' : 'help_request',
                'editorial_origin' => $capsule->editorial_origin,
            ],
            'version' => [
                'version_label' => $version->version_label,
                'body' => $version->body,
                'limits' => $version->limits,
                'content_digest' => $version->content_digest,
                'published_at' => $version->published_at?->toIso8601String(),
                'technologies' => $technologies,
                'contributors' => $contributors,
            ],
            'history' => array_map(static fn (CapsuleVersion $historicVersion): array => [
                'version_label' => $historicVersion->version_label,
                'published_at' => $historicVersion->published_at?->toIso8601String(),
            ], $history),
        ];
    }
}
