<?php

namespace App\Services\Offer;

use App\Models\Calculation;
use App\Models\Meeting;
use App\Models\Offer;
use DomainException;

class OfferRulesService
{
    public function ensureOneOfferPerMeeting(Meeting $meeting): void
    {
        $exists = Offer::query()
            ->where('meeting_id', $meeting->id)
            ->exists();

        if ($exists) {
            throw new DomainException('Only one offer is allowed per meeting.');
        }
    }

    public function ensureCalculationNotExpired(Calculation $calculation): void
    {
        if ($calculation->valid_until && $calculation->valid_until->isPast()) {
            throw new DomainException('Calculation has expired.');
        }
    }
}
