<?php

namespace App\Services\Notifications;

use App\Exceptions\NotificationStorageFailed;
use App\Models\InternalNotification;
use App\Models\User;
use App\Queries\Notifications\NotificationQuery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class MarkNotificationService
{
    public function __construct(private readonly NotificationQuery $query) {}

    public function mark(User $actor, string $id, bool $read): InternalNotification
    {
        try {
            return DB::transaction(function () use ($actor, $id, $read): InternalNotification {
                $current = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $notification = $this->query->visible($current)->whereKey($id)->lockForUpdate()->firstOrFail();
                $notification->read_at = $read ? ($notification->read_at ?? now()->utc()->toImmutable()) : null;
                $notification->save();

                return $notification;
            });
        } catch (QueryException) {
            throw new NotificationStorageFailed('Le statut de lecture n’a pas été enregistré.');
        }
    }
}
