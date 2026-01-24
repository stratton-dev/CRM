<?php

namespace Tests\Unit;

use App\Actions\Calculation\GenerateCalculationAction;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateCalculationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_calculation_generation_when_meeting_is_expired(): void
    {
        $client = Client::create([
            'name' => 'Acme Sp. z o.o.',
            'nip' => '1112223334',
            'address_line1' => 'Main 4',
            'postal_code' => '00-004',
            'city' => 'Warsaw',
            'country' => 'PL',
        ]);

        $user = User::factory()->create();

        $meeting = Meeting::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'status' => 'open',
            'valid_until' => now()->subDay(),
        ]);

        $action = app(GenerateCalculationAction::class);

        $this->expectException(\DomainException::class);
        $action->execute($meeting, 10, 1000);
    }
}
