<?php

namespace App\Http\Resources\Projects;

use App\Models\Project;
use App\Models\Technology;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
final class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'owner' => ['id' => $this->owner_id, 'handle' => $this->owner->handle],
            'name' => $this->name, 'summary' => $this->summary, 'description' => $this->description,
            'project_stage' => $this->project_stage->value, 'publication_state' => $this->publication_state->value,
            'help_sought' => $this->help_sought, 'demo_url' => $this->demo_url, 'repository_url' => $this->repository_url,
            'lock_version' => $this->lock_version, 'published_at' => $this->published_at?->toISOString(),
            'technologies' => $this->technologies->map(fn (Technology $technology): array => ['id' => $technology->id, 'slug' => $technology->slug, 'name' => $technology->name])->all(),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString()];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }
}
