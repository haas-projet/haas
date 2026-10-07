<?php

namespace App\Http\Resources\Collaboration;

use App\Models\Comment;
use App\Support\Collaboration\CommentMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
final class CommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'request_id' => $this->request_id, 'author' => ['id' => $this->author?->id, 'handle' => $this->author?->handle], 'body' => $this->body,
            'body_html' => app(CommentMarkdown::class)->render($this->body), 'lock_version' => $this->lock_version, 'created_at' => $this->created_at?->toISOString(), 'edited_at' => $this->edited_at?->toISOString()];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }
}
