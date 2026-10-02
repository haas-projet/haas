<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserSummaryResource extends JsonResource
{
    /** @return array{id: string, handle: string} */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'handle' => $this->handle];
    }
}
