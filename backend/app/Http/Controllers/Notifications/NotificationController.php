<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Requests\Notifications\MarkNotificationRequest;
use App\Http\Requests\Notifications\NotificationRequest;
use App\Http\Resources\Notifications\NotificationCollection;
use App\Http\Resources\Notifications\NotificationResource;
use App\Queries\Notifications\NotificationQuery;
use App\Services\Notifications\MarkNotificationService;

final class NotificationController
{
    public function index(NotificationRequest $request, NotificationQuery $query): NotificationCollection
    {
        return (new NotificationCollection($query->page($request->member(), $request->pageData())))->additional(['unread_count' => $query->unread($request->member())]);
    }

    public function update(MarkNotificationRequest $request, string $id, MarkNotificationService $service): NotificationResource
    {
        return new NotificationResource($service->mark($request->member(), $id, $request->validated('read')));
    }
}
