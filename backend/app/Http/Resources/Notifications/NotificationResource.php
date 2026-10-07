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
        return ['id' => $this->id, 'kind' => $this->kind, 'message' => $this->kind === 'comment.created' ? 'Un commentaire a été ajouté à votre demande.' : 'Une décision de modération concerne votre profil.',
            'target_path' => $this->kind === 'comment.created' ? '/requests/'.$this->comment?->request_id : '/me/profile', 'read_at' => $this->read_at?->toIso8601String(), 'created_at' => $this->created_at->toIso8601String()];
    }
}
