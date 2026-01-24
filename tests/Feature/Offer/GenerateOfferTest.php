<?php

namespace Tests\Feature\Offer;

use App\Actions\Offer\GenerateOfferAction;
use App\Models\Calculation;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_offer_generation_when_calculation_is_expired(): void
    {
        $client = Client::create([
            'name' => 'Acme Sp. z o.o.',
            'nip' => '3334445556',
            'address_line1' => 'Main 6',
            'postal_code' => '00-006',
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

        $calculation = Calculation::create([
            'meeting_id' => $meeting->id,
            'employee_count' => 10,
            'savings_amount' => 1000,
            'valid_until' => now()->subDay(),
        ]);

        $action = app(GenerateOfferAction::class);

        $this->expectException(\DomainException::class);
        $action->execute($meeting, $calculation);
    }
}
