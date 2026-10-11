<?php

namespace App\Http\Resources\Notifications;

use App\Models\InternalNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InternalNotification */
final class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'kind' => $this->kind, 'message' => match ($this->kind) {
            'comment.created' => 'Un commentaire a été ajouté à votre demande.',
            'proposal.created' => 'Une proposition a été ajoutée à votre demande.',
            'resolution.accepted' => 'L’auteur a accepté votre proposition dans son contexte.',
            default => 'Une décision de modération concerne votre profil.',
        },
            'target_path' => match ($this->kind) {
                'comment.created' => '/requests/'.$this->comment?->request_id,
                'proposal.created' => '/requests/'.$this->proposal?->request_id,
                'resolution.accepted' => '/requests/'.$this->resolution?->request_id,
                default => '/me/profile',
            }, 'read_at' => $this->read_at?->toIso8601String(), 'created_at' => $this->created_at->toIso8601String()];
    }
}
