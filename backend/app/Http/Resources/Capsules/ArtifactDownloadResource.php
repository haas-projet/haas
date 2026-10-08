<?php

declare(strict_types=1);

namespace App\Http\Resources\Capsules;

use App\Models\Capsules\Artifact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * B27 — Resource émise à `GET /artifact`. N'expose NI `private_path`,
 * NI chemin disque, NI URL signée brute distincte des champs du
 * contrat ; aucune donnée privée (owner_id, email, etc.).
 *
 * @property-read array{artifact: Artifact, download_url: string, expires_at: Carbon} $resource
 */
final class ArtifactDownloadResource extends JsonResource
{
    /** @param array{artifact: Artifact, download_url: string, expires_at: Carbon} $resource */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{artifact: Artifact, download_url: string, expires_at: Carbon} $data */
        $data = $this->resource;
        $artifact = $data['artifact'];

        return [
            'id' => $artifact->id,
            'sha256' => (string) $artifact->getAttribute('sha256'),
            'size' => (int) $artifact->getAttribute('size'),
            'notices_path' => (string) $artifact->getAttribute('notices_path'),
            'download_url' => $data['download_url'],
            'expires_at' => $data['expires_at']->toIso8601String(),
        ];
    }
}
