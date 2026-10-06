<?php

namespace App\Http\Requests\Notifications;

final class MarkNotificationRequest extends NotificationRequest
{
    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['read' => ['required', 'boolean:strict']];
    }
}
