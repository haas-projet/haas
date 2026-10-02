<?php

namespace App\Http\Resources\Identity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AccountMailResource extends JsonResource
{
    /** @return array{message: string} */
    public function toArray(Request $request): array
    {
        return ['message' => (string) $this->resource];
    }
}
