<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Meeting\MeetingRulesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_when_meeting_is_expired(): void
    {
        $client = Client::create([
            'name' => 'Acme Sp. z o.o.',
            'nip' => '5556667778',
            'address_line1' => 'Main 3',
            'postal_code' => '00-003',
            'city' => 'Warsaw',
            'country' => 'PL',
        ]);

        $user = User::factory()->create();

        $meeting = Meeting::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'valid_until' => now()->subDay(),
        ]);

        $service = new MeetingRulesService();

        $this->expectException(\DomainException::class);
        $service->ensureNotExpired($meeting);
    }
}
