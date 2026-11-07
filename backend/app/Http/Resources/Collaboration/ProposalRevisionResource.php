<?php

namespace App\Http\Resources\Collaboration;

use App\Models\ProposalRevision;
use App\Support\Collaboration\CommentMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProposalRevision */
final class ProposalRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $markdown = app(CommentMarkdown::class);

        return [
            'id' => $this->id, 'proposal_version' => $this->proposal_version, 'action' => $this->action,
            'diagnosis' => $this->diagnosis, 'diagnosis_html' => $markdown->render($this->diagnosis),
            'fix' => $this->fix, 'fix_html' => $markdown->render($this->fix),
            'verification' => $this->verification, 'verification_html' => $markdown->render($this->verification),
            'limits' => $this->limits, 'limits_html' => $markdown->render($this->limits),
            'code' => $this->code, 'occurred_at' => $this->occurred_at->toISOString(),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }
}
