<?php

namespace App\Listeners;

use App\Contracts\BroadcastsToReverb;
use App\Models\CrmEvent;
use App\Models\CrmEventLog;

class LogCrmEvent
{
    public function handle(BroadcastsToReverb $event): void
    {
        $eventKey = $event->broadcastName();
        $payload = $event->broadcastPayload();
        $eventModel = CrmEvent::query()->where('key', $eventKey)->first();

        CrmEventLog::create([
            'event_id' => $eventModel?->id,
            'event_key' => $eventKey,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
