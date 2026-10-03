<?php

namespace App\Http\Resources\Identity;

use App\Models\User;
use App\Support\Identity\GithubProfileLink;
use App\Support\Identity\ProfileInitials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class PublicProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'handle' => $this->handle,
            'avatar_initials' => ProfileInitials::fromHandle($this->handle),
            'bio' => $this->profile->bio ?? '', 'country' => $this->profile?->country,
            'primary_language' => $this->profile->primary_language ?? 'fr',
            'github_url' => GithubProfileLink::allowed($this->profile?->github_url) ? $this->profile?->github_url : null,
            'technologies' => TechnologyResource::collection($this->technologies),
            'is_demo' => $this->is_demo,
            // Les domaines de contributions ne sont pas encore livrés : aucun zéro fictif.
            'contributions' => null,
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }
}
