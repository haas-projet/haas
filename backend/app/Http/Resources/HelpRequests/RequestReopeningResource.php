<?php

namespace App\Http\Resources\HelpRequests;

use App\Models\RequestReopening;
use App\Support\Collaboration\CommentMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RequestReopening */
final class RequestReopeningResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'request_id' => $this->request_id, 'resolution_id' => $this->resolution_id,
            'reopened_by' => ['id' => $this->reopened_by, 'handle' => $this->author?->handle],
            'reason' => $this->reason, 'reason_html' => app(CommentMarkdown::class)->render($this->reason),
            'reopened_at' => $this->reopened_at->toISOString(), 'request_version_before' => $this->request_version_before,
            'request_version_after' => $this->request_version_after];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }
}
