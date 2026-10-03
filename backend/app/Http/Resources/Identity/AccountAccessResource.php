<?php

namespace App\Http\Resources\Identity;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AccountAccessResource extends JsonResource
{
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }

    /** @return array{message: string, contact_email: ?string} */
    public function toArray(Request $request): array
    {
        return ['message' => $this->resource['message'], 'contact_email' => $this->resource['contact_email']];
    }
}
