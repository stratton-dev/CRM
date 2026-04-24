<?php

namespace App\Events\Notifications;

class NotificationCreated extends NotificationEvent
{
    public function broadcastName(): string
    {
        return 'notifications.created';
    }

    protected function action(): string
    {
        return 'created';
    }
}
