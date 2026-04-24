<?php

namespace App\Actions\Offer;

use App\Events\OfferOpened;
use App\Models\Offer;
use DomainException;

class OpenOfferAction
{
    public function execute(string $token): Offer
    {
        $offer = Offer::query()
            ->where('token', $token)
            ->first();

        if (!$offer) {
            throw new DomainException('Offer token is invalid.');
        }

        if ($offer->expires_at && $offer->expires_at->isPast()) {
            throw new DomainException('Offer has expired.');
        }

        if (!$offer->opened_at) {
            $offer->forceFill(['opened_at' => now()])->save();
        }

        $offer->loadMissing('meeting.user');
        event(new OfferOpened($offer));

        return $offer;
    }
}
