<?php

namespace App\Actions\Offer;

use App\Models\Calculation;
use App\Models\Meeting;
use App\Models\Offer;
use App\Services\Offer\OfferRulesService;

class GenerateOfferAction
{
    public function __construct(private OfferRulesService $offerRulesService)
    {
    }

    public function execute(Meeting $meeting, Calculation $calculation, array $attributes = []): Offer
    {
        $this->offerRulesService->ensureOneOfferPerMeeting($meeting);
        $this->offerRulesService->ensureCalculationNotExpired($calculation);

        return Offer::create(array_merge([
            'meeting_id' => $meeting->id,
            'company_id' => $meeting->client_id,
        ], $attributes));
    }
}
