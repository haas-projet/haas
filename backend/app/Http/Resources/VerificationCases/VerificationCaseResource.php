<?php

declare(strict_types=1);

namespace App\Http\Resources\VerificationCases;

use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BV202 — Resource publique du cas. N'expose aucun champ interne : pas de
 * trace d'audit, pas de contenu de brouillon tant que l'acteur n'est pas
 * l'auteur. Les révisions figées sont lisibles par tout acteur habilité ;
 * les brouillons ne sont renvoyés qu'à leur auteur.
 *
 * @property-read VerificationCase $resource
 */
final class VerificationCaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $case = $this->resource;
        $actorId = $request->user()?->getAuthIdentifier();
        $isAuthor = $actorId !== null && (string) $actorId === $case->author_id;

        return [
            'id' => $case->id,
            'author_id' => $case->author_id,
            'parent' => [
                'kind' => $case->help_request_id !== null ? 'help_request' : 'capsule_version',
                'help_request_id' => $case->help_request_id,
                'capsule_version_id' => $case->capsule_version_id,
            ],
            'state' => $case->state->value,
            'current_revision' => (int) $case->current_revision,
            'lock_version' => (int) $case->lock_version,
            'created_at' => $case->created_at?->toIso8601String(),
            'updated_at' => $case->updated_at?->toIso8601String(),
            'revisions' => $case->revisions
                ->sortBy('revision_no')
                ->values()
                ->map(fn (VerificationCaseRevision $revision): array => $this->revisionArray($revision, $isAuthor))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function revisionArray(VerificationCaseRevision $revision, bool $isAuthor): array
    {
        $frozen = $revision->submitted_at !== null;
        $exposeBody = $frozen || $isAuthor;

        return [
            'revision_no' => (int) $revision->revision_no,
            'digest' => $revision->digest,
            'submitted_at' => $revision->submitted_at?->toIso8601String(),
            'body' => $exposeBody ? $revision->body : null,
        ];
    }
}
