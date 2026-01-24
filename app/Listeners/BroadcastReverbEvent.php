<?php

namespace App\Listeners;

use App\Contracts\BroadcastsToReverb;
use App\Events\Broadcast\ReverbBroadcast;

class BroadcastReverbEvent
{
    public function handle(BroadcastsToReverb $event): void
    {
        $channels = array_values(array_unique(array_filter($event->broadcastChannels())));
        if ($channels === []) {
            return;
        }

        broadcast(new ReverbBroadcast(
            $event->broadcastName(),
            $channels,
            $event->broadcastPayload()
        ));
    }
}
