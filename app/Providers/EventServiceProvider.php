<?php

namespace App\Providers;

use App\Events\ChatMessageSent;
use App\Events\MeetingCreated;
use App\Events\Notifications\NotificationCreated;
use App\Events\Notifications\NotificationDeleted;
use App\Events\Notifications\NotificationUpdated;
use App\Events\OfferOpened;
use App\Events\Structure\StructureUserCreated;
use App\Events\Structure\StructureUserMoved;
use App\Events\Structure\StructureUserRemoved;
use App\Events\Structure\StructureUserRestored;
use App\Listeners\BroadcastReverbEvent;
use App\Listeners\LogCrmEvent;
use App\Listeners\NotifyManagerAboutMeeting;
use App\Listeners\NotifyOfferOpened;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ChatMessageSent::class => [
            BroadcastReverbEvent::class,
        ],
        MeetingCreated::class => [
            NotifyManagerAboutMeeting::class,
            BroadcastReverbEvent::class,
            LogCrmEvent::class,
        ],
        OfferOpened::class => [
            NotifyOfferOpened::class,
            BroadcastReverbEvent::class,
            LogCrmEvent::class,
        ],
        StructureUserCreated::class => [
            BroadcastReverbEvent::class,
            LogCrmEvent::class,
        ],
        StructureUserMoved::class => [
            BroadcastReverbEvent::class,
            LogCrmEvent::class,
        ],
        StructureUserRemoved::class => [
            BroadcastReverbEvent::class,
            LogCrmEvent::class,
        ],
        StructureUserRestored::class => [
            BroadcastReverbEvent::class,
            LogCrmEvent::class,
        ],
        NotificationCreated::class => [
            BroadcastReverbEvent::class,
            LogCrmEvent::class,
        ],
        NotificationUpdated::class => [
            BroadcastReverbEvent::class,
            LogCrmEvent::class,
        ],
        NotificationDeleted::class => [
            BroadcastReverbEvent::class,
            LogCrmEvent::class,
        ],
    ];
}
