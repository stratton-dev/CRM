<?php

namespace Tests\Feature;

use App\Actions\Meeting\CreateMeetingAction;
use App\Events\MeetingCreated;
use App\Models\Client;
use App\Models\ClientConsent;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MeetingCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_meeting_when_required_consents_are_present(): void
    {
        Event::fake();

        $client = Client::create([
            'name' => 'Acme Sp. z o.o.',
            'nip' => '9876543210',
            'address_line1' => 'Main 2',
            'postal_code' => '00-002',
            'city' => 'Warsaw',
            'country' => 'PL',
        ]);

        $requiredConsent = Consent::create([
            'code' => 'privacy',
            'description' => 'Privacy policy',
            'required' => true,
        ]);

        ClientConsent::create([
            'client_id' => $client->id,
            'consent_id' => $requiredConsent->id,
            'accepted_at' => now(),
            'source' => 'meeting',
        ]);

        $user = User::factory()->create();

        $action = app(CreateMeetingAction::class);
        $meeting = $action->execute($client, $user);

        $this->assertSame($client->id, $meeting->client_id);
        $this->assertSame($user->id, $meeting->user_id);
        $this->assertSame(now()->addDays(90)->toDateString(), $meeting->valid_until->toDateString());

        Event::assertDispatched(MeetingCreated::class);
    }
}
