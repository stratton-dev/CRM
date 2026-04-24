<?php

namespace App\Services\Client;

use App\Models\Client;
use App\Models\ClientConsent;
use App\Models\Consent;
use DomainException;

class ClientValidationService
{
    public function ensureRequiredConsents(Client $client): void
    {
        $requiredConsentIds = Consent::query()
            ->where('required', true)
            ->pluck('id');

        if ($requiredConsentIds->isEmpty()) {
            return;
        }

        $acceptedConsentIds = ClientConsent::query()
            ->where('client_id', $client->id)
            ->whereNotNull('accepted_at')
            ->pluck('consent_id')
            ->unique();

        $missing = $requiredConsentIds->diff($acceptedConsentIds);

        if ($missing->isNotEmpty()) {
            throw new DomainException('Client is missing required consents.');
        }
    }
}
