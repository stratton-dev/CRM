<?php

namespace App\Events\Notifications;

class NotificationUpdated extends NotificationEvent
{
    public function broadcastName(): string
    {
        return 'notifications.updated';
    }

    protected function action(): string
    {
        return 'updated';
    }
}
