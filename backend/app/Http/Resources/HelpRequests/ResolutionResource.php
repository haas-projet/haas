<?php

namespace App\Http\Resources\HelpRequests;

use App\Models\Resolution;
use App\Support\Collaboration\CommentMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Resolution */
final class ResolutionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'request_id' => $this->request_id, 'proposal_id' => $this->proposal_id,
            'proposal_version' => $this->proposal_version, 'environment' => $this->environment,
            'validation_note' => $this->validation_note,
            'validation_note_html' => app(CommentMarkdown::class)->render($this->validation_note),
            'accepted_by' => ['id' => $this->accepted_by, 'handle' => $this->acceptor?->handle],
            'self_resolution' => $this->accepted_by === $this->proposal?->author_id,
            'accepted_at' => $this->accepted_at->toISOString(), 'revoked_at' => $this->revoked_at?->toISOString(),
            'request_lock_version' => $this->request?->lock_version,
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }
}
