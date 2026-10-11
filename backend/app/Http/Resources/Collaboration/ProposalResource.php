<?php

namespace App\Http\Resources\Collaboration;

use App\Models\Proposal;
use App\Support\Collaboration\CommentMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Proposal */
final class ProposalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $markdown = app(CommentMarkdown::class);

        return [
            'id' => $this->id, 'request_id' => $this->request_id,
            'author' => ['id' => $this->author?->id, 'handle' => $this->author?->handle],
            'diagnosis' => $this->diagnosis, 'diagnosis_html' => $markdown->render($this->diagnosis),
            'fix' => $this->fix, 'fix_html' => $markdown->render($this->fix),
            'verification' => $this->verification, 'verification_html' => $markdown->render($this->verification),
            'limits' => $this->limits, 'limits_html' => $markdown->render($this->limits),
            'code' => $this->code, 'state' => $this->state->value, 'lock_version' => $this->lock_version,
            'created_at' => $this->created_at?->toISOString(), 'edited_at' => $this->edited_at?->toISOString(),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }
}
