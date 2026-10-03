<?php

namespace App\Data\Notifications;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class NotificationEvent
{
    public function __construct(public string $eventId, public string $recipientId, public string $kind)
    {
        if (! Str::isUuid($eventId) || ! Str::isUuid($recipientId) || $kind !== 'profile.moderated') {
            throw new InvalidArgumentException('Événement de notification non pris en charge.');
        }
    }
}
