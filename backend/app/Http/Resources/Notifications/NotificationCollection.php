<?php

namespace App\Http\Resources\Notifications;

use App\Http\Resources\PaginatedResourceCollection;

final class NotificationCollection extends PaginatedResourceCollection
{
    public $collects = NotificationResource::class;
}
