<?php

namespace Tests\Feature\Offer;

use App\Models\Client;
use App\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOfferAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_offer_access_is_blocked(): void
    {
        $client = Client::create([
            'name' => 'Acme Sp. z o.o.',
            'nip' => '5551112223',
            'address_line1' => 'Main 10',
            'postal_code' => '00-010',
            'city' => 'Warsaw',
            'country' => 'PL',
        ]);

        $offer = Offer::create([
            'company_id' => $client->id,
            'token' => 'expired-token',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/offers/'.$offer->token);

        $response->assertStatus(403);
    }
}
