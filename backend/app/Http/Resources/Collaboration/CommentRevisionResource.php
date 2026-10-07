<?php

namespace App\Http\Resources\Collaboration;

use App\Models\CommentRevision;
use App\Support\Collaboration\CommentMarkdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommentRevision */
final class CommentRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'comment_version' => $this->comment_version, 'action' => $this->action, 'body' => $this->body,
            'body_html' => app(CommentMarkdown::class)->render($this->body), 'occurred_at' => $this->occurred_at->toISOString()];
    }
}
