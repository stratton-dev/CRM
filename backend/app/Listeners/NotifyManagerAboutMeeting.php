<?php

namespace App\Listeners;

use App\Events\MeetingCreated;

class NotifyManagerAboutMeeting
{
    public function handle(MeetingCreated $event): void
    {
        // Notification dispatch will be implemented in automation layer.
    }
}
