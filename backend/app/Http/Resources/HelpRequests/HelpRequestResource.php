<?php

namespace App\Http\Resources\HelpRequests;

use App\Models\HelpRequest;
use App\Models\Technology;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HelpRequest */
final class HelpRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'author' => ['id' => $this->author_id, 'handle' => $this->author?->handle],
            'title' => $this->title, 'goal' => $this->goal, 'expected' => $this->expected, 'observed' => $this->observed,
            'attempts' => $this->attempts, 'environment' => $this->environment, 'code' => $this->code,
            'code_language' => $this->code_language, 'primary_language' => $this->primary_language,
            'reproduction_url' => $this->reproduction_url, 'help_intent' => $this->help_intent->value,
            'state' => $this->state->value, 'lock_version' => $this->lock_version,
            'technologies' => $this->technologies->map(fn (Technology $technology): array => [
                'id' => $technology->id, 'slug' => $technology->slug, 'name' => $technology->name,
                'version_label' => $technology->getRelation('pivot')->getAttribute('version_label'),
            ])->all(),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }
}
