<?php

namespace Tests\Unit\Offer;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\Offer;
use App\Models\User;
use App\Services\Offer\OfferRulesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_creating_a_second_offer_for_the_same_meeting(): void
    {
        $client = Client::create([
            'name' => 'Acme Sp. z o.o.',
            'nip' => '2223334445',
            'address_line1' => 'Main 5',
            'postal_code' => '00-005',
            'city' => 'Warsaw',
            'country' => 'PL',
        ]);

        $user = User::factory()->create();

        $meeting = Meeting::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'status' => 'open',
            'valid_until' => now()->addDays(30),
        ]);

        Offer::create([
            'meeting_id' => $meeting->id,
            'company_id' => $client->id,
        ]);

        $service = new OfferRulesService();

        $this->expectException(\DomainException::class);
        $service->ensureOneOfferPerMeeting($meeting);
    }
}
