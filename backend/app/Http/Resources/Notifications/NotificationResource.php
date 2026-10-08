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
        $message = match ($this->kind) {
            'comment.created' => 'Un commentaire a été ajouté à votre demande.',
            'capsule.review.changes_requested' => 'Des corrections ont été demandées sur votre brouillon de capsule.',
            default => 'Une décision de modération concerne votre profil.',
        };
        $target = match ($this->kind) {
            'comment.created' => '/requests/'.$this->comment?->request_id,
            'capsule.review.changes_requested' => '/capsules/'.$this->capsuleReview?->version?->capsule_id.'/versions/'.$this->capsuleReview?->version_id.'/edition',
            default => '/me/profile',
        };

        return ['id' => $this->id, 'kind' => $this->kind, 'message' => $message, 'target_path' => $target,
            'read_at' => $this->read_at?->toIso8601String(), 'created_at' => $this->created_at->toIso8601String()];
    }
}
