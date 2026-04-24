<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientConsent;
use App\Models\Consent;
use App\Services\Client\ClientValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientConsentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_meeting_creation_when_required_consents_are_missing(): void
    {
        $client = Client::create([
            'name' => 'Acme Sp. z o.o.',
            'nip' => '1234567890',
            'address_line1' => 'Main 1',
            'postal_code' => '00-001',
            'city' => 'Warsaw',
            'country' => 'PL',
        ]);

        $requiredA = Consent::create([
            'code' => 'privacy',
            'description' => 'Privacy policy',
            'required' => true,
        ]);
        Consent::create([
            'code' => 'terms',
            'description' => 'Terms',
            'required' => true,
        ]);
        Consent::create([
            'code' => 'marketing',
            'description' => 'Marketing',
            'required' => false,
        ]);

        ClientConsent::create([
            'client_id' => $client->id,
            'consent_id' => $requiredA->id,
            'accepted_at' => now(),
            'source' => 'meeting',
        ]);

        $service = new ClientValidationService();

        $this->expectException(\DomainException::class);
        $service->ensureRequiredConsents($client);
    }
}
