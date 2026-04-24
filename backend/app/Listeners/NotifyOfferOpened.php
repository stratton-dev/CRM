<?php

namespace App\Listeners;

use App\Events\OfferOpened;

class NotifyOfferOpened
{
    public function handle(OfferOpened $event): void
    {
        // Notification dispatch will be implemented in automation layer.
    }
}
