<?php

namespace App\Events\Notifications;

class NotificationDeleted extends NotificationEvent
{
    public function broadcastName(): string
    {
        return 'notifications.deleted';
    }

    protected function action(): string
    {
        return 'deleted';
    }
}
