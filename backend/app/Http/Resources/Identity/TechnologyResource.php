<?php

namespace App\Http\Resources\Identity;

use App\Models\Technology;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Technology */
final class TechnologyResource extends JsonResource
{
    /** @return array{id: string, slug: string, name: string} */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'slug' => $this->slug, 'name' => $this->name];
    }
}
